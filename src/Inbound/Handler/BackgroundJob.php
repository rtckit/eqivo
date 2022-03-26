<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Inbound\Handler;

use RTCKit\Eqivo\{
    Core,
    EventEnum,
    HangupCauseEnum,
    StatusEnum
};

use stdClass as Event;

class BackgroundJob implements HandlerInterface
{
    use HandlerTrait;

    /** @var EventEnum */
    public const EVENT = EventEnum::BACKGROUND_JOB;

    public function execute(Core $core, Event $event): void
    {
        if (!isset($event->{'Job-UUID'})) {
            return;
        }

        $job = $core->getJob($event->{'Job-UUID'});

        if (!isset($job)) {
            return;
        }

        switch ($job->command) {
            case 'originate':
                $callRequest = $core->getCallRequest($job->callRequest->uuid);

                if (!isset($callRequest)) {
                    break;
                }

                $parts = explode(' ', isset($event->_body) ? trim($event->_body) : '', 2);
                $groupCallVar = "variable_{$this->app->config->appPrefix}_group_call";

                if ($job->group) {
                    if (strpos($parts[0], '+OK') === 0) {
                        $this->app->inboundServer->logger->warning("GroupCall Attempt Done for RequestUUID {$callRequest->uuid} ({$parts[1]})");
                    } else {
                        $this->app->inboundServer->logger->warning("GroupCall Attempt Failed for RequestUUID {$callRequest->uuid} ({$parts[1]})");
                    }

                    $callRequest->core->removeCallRequest($callRequest->uuid);

                    break;
                }

                if (strpos($parts[0], '+OK') === false) {
                    if (strpos($parts[0], '-USAGE') === 0) {
                        $parts[1] = HangupCauseEnum::INVALID_NUMBER_FORMAT->value;
                    }

                    if (isset($callRequest->status) && in_array($callRequest->status, [StatusEnum::Ringing, StatusEnum::EarlyMedia])) {
                        $this->app->inboundServer->logger->warning("Call Attempt Done ({$callRequest->status->value}) for RequestUUID {$callRequest->uuid} but Failed ({$parts[1]})");
                        $this->app->inboundServer->logger->debug("Notify Call success for RequestUUID {$callRequest->uuid}");
                        $job->deferred->resolve(true);
                        break;
                    } else if (!count($callRequest->gateways)) {
                        $this->app->inboundServer->logger->warning("Call Failed for RequestUUID {$callRequest->uuid} but No More Gateways ({$parts[1]})");
                        $this->app->inboundServer->logger->debug("Notify Call success for RequestUUID {$callRequest->uuid}");
                        $job->deferred->resolve(true);
                        $this->app->inboundServer->controller->hangupCompleted(event: $event, reason: HangupCauseEnum::from($parts[1]), callRequest: $callRequest);
                        break;
                    } else {
                        $this->app->inboundServer->logger->warning("Call Failed without Ringing/EarlyMedia for RequestUUID {$callRequest->uuid} - Retrying Now ({$parts[1]})");
                        $this->app->inboundServer->logger->debug("Notify Call retry for RequestUUID {$callRequest->uuid}");
                        $job->deferred->resolve(false);
                        break;
                    }
                }

                break;

            case 'conference':
                $result = isset($event->_body) ? trim($event->_body) : '';
                $this->app->inboundServer->logger->info("Conference Api Response for JobUUID {$job->uuid} -- {$result}");
                break;
        }

        $core->removeJob($event->{'Job-UUID'});
    }
}
