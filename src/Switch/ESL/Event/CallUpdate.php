<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Switch\ESL\Event;

use RTCKit\Eqivo\Signal\Channel as ChannelSignal;
use RTCKit\FiCore\Switch\ESL\Event\{
    HandlerInterface,
    HandlerTrait,
};
use RTCKit\FiCore\Switch\{
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
        $appVar = "variable_{$this->app->config->appPrefix}_app";

        if (!isset($event->{$appVar}) || ($event->{$appVar} !== 'true')) {
            if (!isset($event->{'Bridged-To'})) {
                return;
            }

            $aLegUuid = $event->{'Bridged-To'};
            $channel = $core->getChannel($aLegUuid);

            if (!isset($channel)) {
                return;
            }

            if (!isset($event->{'Unique-ID'})) {
                return;
            }

            $bLegUuid = $event->{'Unique-ID'};

            if (!isset($event->variable_endpoint_disposition) || ($event->variable_endpoint_disposition !== 'ANSWER')) {
                return;
            }

            $signalAttnVar = "variable_{$this->app->config->appPrefix}_dial_signal_attn";

            if (!isset($event->{$signalAttnVar})) {
                return;
            }

            $bridgeSignal = new ChannelSignal\Bridge();
            $bridgeSignal->attn = $event->{$signalAttnVar};
            $bridgeSignal->channel = $channel;
            $bridgeSignal->bridged = $bLegUuid;
            $bridgeSignal->status = StatusEnum::Answer;

            $this->app->signalProducer->produce($bridgeSignal);
        }
    }
}
