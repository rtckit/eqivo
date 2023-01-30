<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Signal\Handler\Channel;

use RTCKit\Eqivo\Signal\Handler\AbstractHandler;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Signal\Channel\Recording as RecordingSignal;

class Recording extends AbstractHandler
{
    public function export(AbstractSignal $signal): array
    {
        assert($signal instanceof RecordingSignal);

        $ret = array_merge($this->app->planProducer->getChannelPayload($signal->channel), [
            'RestApiServer' => $this->getRestServerAdvertisedHost(),
            'RecordFile' => $signal->medium,
            'RecordDuration' => $signal->duration,
        ]);

        if (isset($signal->terminator)) {
            $ret['Digits'] = $signal->terminator;
        }

        return $ret;
    }
}
