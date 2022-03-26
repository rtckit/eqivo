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

class CallUpdate implements HandlerInterface
{
    use HandlerTrait;

    /** @var EventEnum */
    public const EVENT = EventEnum::CALL_UPDATE;

    public function execute(Core $core, Event $event): void
    {
        $eqivoAppVar = "variable_{$this->app->config->appPrefix}_app";

        if (!isset($event->{$eqivoAppVar}) || ($event->{$eqivoAppVar} !== 'true')) {
            if (!isset($event->{'Bridged-To'})) {
                return;
            }

            $aLegUuid = $event->{'Bridged-To'};

            if (!isset($event->{'Unique-To'})) {
                return;
            }

            $bLegUuid = $event->{'Unique-To'};

            if (!isset($event->variable_endpoint_disposition) || ($event->variable_endpoint_disposition !== 'ANSWER')) {
                return;
            }

            $eqivoCallbackUrlVar = "variable_{$this->app->config->appPrefix}_dial_callback_url";
            $eqivoCallbackUrlMethod = "variable_{$this->app->config->appPrefix}_dial_callback_method";

            if (!isset($event->{$eqivoCallbackUrlVar}, $event->{$eqivoCallbackUrlMethod})) {
                return;
            }

            $this->app->inboundServer->controller->fireEventCallback(
                $event,
                $event->{$eqivoCallbackUrlVar},
                $event->{$eqivoCallbackUrlMethod},
                [
                    'DialBLegUUID' => $bLegUuid,
                    'DialALegUUID' => $aLegUuid,
                    'DialBLegStatus' => StatusEnum::Answer->value,
                    'CallUUID' => $aLegUuid,
                    'CoreUUID' => $core->uuid,
                ]
            );
        }
    }
}
