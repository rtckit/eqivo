<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Signal\Handler\Channel;

use RTCKit\Eqivo\Signal\Handler\AbstractHandler;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Signal\Channel\MachineDetection as MachineDetectionSignal;

class MachineDetection extends AbstractHandler
{
    public function export(AbstractSignal $signal): array
    {
        assert($signal instanceof MachineDetectionSignal);

        return (array_merge($this->app->planProducer->getChannelPayload($signal->channel), [
            'RestApiServer' => $this->getRestServerAdvertisedHost(),
            'AnsweredBy' => $signal->result->value,
            'MachineDetectionDuration' => (int)($signal->duration * 1000),
        ]));
    }
}
