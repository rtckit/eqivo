<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Signal\Handler\Channel;

use RTCKit\Eqivo\Signal\Handler\AbstractHandler;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Signal\Channel\Heartbeat as HeartbeatSignal;

class Heartbeat extends AbstractHandler
{
    public function export(AbstractSignal $signal): array
    {
        assert($signal instanceof HeartbeatSignal);

        $answeredAt = (float)$signal->event->{'Caller-Channel-Answered-Time'} / 1e6;
        $elapsed = $signal->timestamp - $answeredAt;

        return (array_merge($this->app->planProducer->getChannelPayload($signal->channel), [
            'RestApiServer' => $this->getRestServerAdvertisedHost(),
            'AnsweredTime' => $answeredAt,
            'HeartbeatTime' => $signal->timestamp,
            'ElapsedTime' => $elapsed,
        ]));
    }
}
