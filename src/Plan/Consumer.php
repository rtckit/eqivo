<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan;

use RTCKit\FiCore\AbstractApp;
use RTCKit\Eqivo\Signal\Channel as ChannelSignal;
use RTCKit\FiCore\Plan\Consumer as FiCoreConsumer;
use RTCKit\FiCore\Switch\{
    Channel,
    EventEnum,
};
use stdClass as Event;

class Consumer extends FiCoreConsumer
{
    public function setApp(AbstractApp $app): static
    {
        parent::setApp($app);

        $this->subscribedEvents .= " {$app->config->appPrefix}::dial";

        return $this;
    }

    public function onEvent(Channel $channel, Event $event): void
    {
        parent::onEvent($channel, $event);

        if (!isset($event->{'Event-Name'})) {
            return;
        }

        switch ($event->{'Event-Name'}) {
            case EventEnum::CHANNEL_BRIDGE->value:
            case EventEnum::CHANNEL_UNBRIDGE->value:
                if (get_class($channel->currentElement) === Dial\Element::class) {
                    $this->pushToEventQueue($channel, $event);
                }
                break;

            case EventEnum::CUSTOM->value:
                if (!empty($channel->currentElement)) {
                    switch (get_class($channel->currentElement)) {
                        case Dial\Element::class:
                            if (
                                ($event->{'Event-Subclass'} === $this->app->config->appPrefix . '::dial') &&
                                ($event->{'Unique-ID'} === $channel->uuid) &&
                                ($event->Action === 'digits-match')
                            ) {
                                $this->logger->debug('Digits match on Dial');

                                if (isset($event->{'Signal-Attn'})) {
                                    $dtmfSignal = new ChannelSignal\DTMF();
                                    $dtmfSignal->attn = $event->{'Signal-Attn'};
                                    $dtmfSignal->channel = $channel;
                                    $dtmfSignal->tones = isset($event->{'Digits-Match'}) ? $event->{'Digits-Match'} : '';

                                    if (isset($event->{'variable_bridge_uuid'})) {
                                        $dtmfSignal->bridged = $event->{'variable_bridge_uuid'};
                                    }

                                    $this->app->signalProducer->produce($dtmfSignal);
                                }
                            }
                            break;
                    }
                }
                break;
        }
    }
}
