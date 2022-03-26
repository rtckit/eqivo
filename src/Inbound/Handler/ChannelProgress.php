<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Inbound\Handler;

use RTCKit\Eqivo\{
    Core,
    EventEnum,
    StatusEnum
};

use stdClass as Event;

class ChannelProgress implements HandlerInterface
{
    use HandlerTrait;

    /** @var EventEnum */
    public const EVENT = EventEnum::CHANNEL_PROGRESS;

    public function execute(Core $core, Event $event): void
    {
        $reqUuidVar = "variable_{$this->app->config->appPrefix}_request_uuid";

        if (!isset($event->{$reqUuidVar}, $event->{'Call-Direction'})) {
            return;
        }

        if ($event->{'Call-Direction'} === 'outbound') {
            $status = ($event->{'Event-Name'} === EventEnum::CHANNEL_PROGRESS->value)
                ? StatusEnum::Ringing
                : StatusEnum::EarlyMedia;
            $ringUrl = '';
            $accountSidVar = "variable_{$this->app->config->appPrefix}_accountsid";
            $accountSid = isset($event->{$accountSidVar}) ? $event->{$accountSidVar} : '';

            $groupCallVar = "variable_{$this->app->config->appPrefix}_group_call";

            if (isset($event->{$groupCallVar}) && ($event->{$groupCallVar} === 'true')) {
                $ringUrlVar = "variable_{$this->app->config->appPrefix}_ring_url";
                $ringUrl = isset($event->{$ringUrlVar}) ? $event->{$ringUrlVar} : '';
            } else {
                $callRequest = $core->getCallRequest($event->{$reqUuidVar});

                if (!isset($callRequest)) {
                    return;
                }

                $this->app->inboundServer->logger->debug("Notify Call success ({$status->value}) for RequestUUID {$callRequest->uuid}");
                $callRequest->job->deferred->resolve(true);

                if (!isset($callRequest->status)) {
                    $callRequest->status = $status;
                    $callRequest->gateways = [];
                    $ringUrl = $callRequest->ringUrl;
                }
            }

            if (isset($ringUrl[0])) {
                $calledNum = '';
                $calledNumVar = "variable_{$this->app->config->appPrefix}_destination_number";

                if (isset($event->{'$calledNumVar'}) && ($event->{'$calledNumVar'} !== '_undef_')) {
                    $calledNum = $event->{'$calledNumVar'};
                } else if (isset($event->{'Caller-Destination-Number'})) {
                    $calledNum = $event->{'Caller-Destination-Number'};
                }

                $calledNum = ltrim($calledNum, '+');
                $callerNum = '';

                if (isset($event->{'Caller-Caller-ID-Number'})) {
                    $callerNum = ltrim($event->{'Caller-Caller-ID-Number'}, '+');
                }

                $callUuid = isset($event->{'Unique-ID'}) ? $event->{'Unique-ID'} : '';

                $this->app->inboundServer->logger->debug("Call from {$callerNum} to {$calledNum} Ringing for RequestUUID {$event->{$reqUuidVar}}");

                $this->app->inboundServer->controller->fireEventCallback(
                    $event,
                    $ringUrl,
                    'POST',
                    [
                        'To' => $calledNum,
                        'RequestUUID' => $event->{$reqUuidVar},
                        'Direction' => $event->{'Call-Direction'},
                        'CallStatus' => $status->value,
                        'From' => $callerNum,
                        'CallUUID' => $callUuid,
                        'CoreUUID' => $core->uuid,
                    ]
                );
            }
        }
    }
}
