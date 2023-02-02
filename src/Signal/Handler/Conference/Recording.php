<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Signal\Handler\Conference;

use RTCKit\Eqivo\Signal\Handler\AbstractHandler;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Signal\Conference\Recording as RecordingSignal;

class Recording extends AbstractHandler
{
    public function export(AbstractSignal $signal): array
    {
        assert($signal instanceof RecordingSignal);

        return (array_merge($this->app->planProducer->getConferencePayload($signal->conference), [
            'RestApiServer' => $this->getRestServerAdvertisedHost(),
            'RecordFile' => $signal->medium,
            'RecordDuration' => $signal->duration * 1000,
        ]));
    }
}
