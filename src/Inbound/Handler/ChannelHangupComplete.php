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

class ChannelHangupComplete implements HandlerInterface
{
    use HandlerTrait;

    /** @var EventEnum */
    public const EVENT = EventEnum::CHANNEL_HANGUP_COMPLETE;

    public function execute(Core $core, Event $event): void
    {
        $eqivoAppVar = "variable_{$this->app->config->appPrefix}_app";
        $eqivoCallbackAlegVar = "variable_{$this->app->config->appPrefix}_dial_callback_aleg";
        $eqivoCallbackUrlVar = "variable_{$this->app->config->appPrefix}_dial_callback_url";
        $eqivoCallbackMethodVar = "variable_{$this->app->config->appPrefix}_dial_callback_method";

        if (!isset($event->{$eqivoAppVar}) || ($event->{$eqivoAppVar} !== 'true')) {
            if (isset($event->{'Hangup-Cause'}) && ($event->{'Hangup-Cause'} === HangupCauseEnum::LOSE_RACE->value)) {
                return;
            }

            if (!isset(
                $event->{$eqivoCallbackAlegVar},
                $event->{$eqivoCallbackUrlVar},
                $event->{$eqivoCallbackMethodVar}
            ))
            {
                return;
            }

            $this->app->inboundServer->controller->fireEventCallback(
                $event,
                $event->{$eqivoCallbackUrlVar},
                $event->{$eqivoCallbackMethodVar},
                [
                    'DialBLegUUID' => $event->{'Unique-ID'},
                    'DialALegUUID' => $event->{$eqivoCallbackAlegVar},
                    'DialBLegStatus' => StatusEnum::Answer->value,
                    'CallUUID' => $event->{$eqivoCallbackAlegVar},
                    'CoreUUID' => $core->uuid,
                ]
            );
            return;
        }

        $direction = $event->{'Call-Direction'};

        if ($direction === 'inbound') {
            $session = $core->getSession($event->{'Unique-ID'});
            $reason = HangupCauseEnum::from($event->{'Hangup-Cause'});
            $this->app->inboundServer->controller->hangupCompleted(event: $event, reason: $reason, session: $session);
        } else {
            $reqUuidVar = "variable_{$this->app->config->appPrefix}_request_uuid";

            if (!isset($event->{$reqUuidVar}) && ($direction === 'outbound')) {
                return;
            }

            $session = $core->getSession($event->{'Unique-ID'});
            $reason = HangupCauseEnum::from($event->{'Hangup-Cause'});
            $groupCallVar = "variable_{$this->app->config->appPrefix}_group_call";
            $hangupUrl = null;
            $callRequest = null;

            if (isset($event->{$groupCallVar}) && ($event->{$groupCallVar} === 'true')) {
                $hangupUrl = $event->{"variable_{$this->app->config->appPrefix}_hangup_url"};
            } else {
                $callRequest = $core->getCallRequest($event->{$reqUuidVar});

                if (!isset($callRequest)) {
                    return;
                }

                if (count($callRequest->gateways)) {
                    $this->app->inboundServer->logger->debug("Call Failed for RequestUUID {$callRequest->uuid} - Retrying ({$reason->value})");
                    $this->app->inboundServer->logger->debug("Notify Call retry for RequestUUID {$callRequest->uuid}");
                    $callRequest->job->deferred->resolve(false);
                    return;
                }

                $hangupUrl = $callRequest->hangupUrl;

                $this->app->inboundServer->logger->debug("Notify Call success for RequestUUID {$callRequest->uuid}");

                if (isset($callRequest->job)) {
                    $callRequest->job->deferred->resolve(true);
                }
            }

            $this->app->inboundServer->controller->hangupCompleted(event: $event, reason: $reason, url: $hangupUrl, callRequest: $callRequest, session: $session);
        }
    }
}
