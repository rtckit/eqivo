<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Signal\Handler\Channel;

use RTCKit\Eqivo\Signal\Channel\DTMF as DTMFSignal;
use RTCKit\Eqivo\Signal\Handler\AbstractHandler;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Signal\Channel\DTMF as FiCoreDTMFSignal;

class DTMF extends AbstractHandler
{
    public function export(AbstractSignal $signal): array
    {
        assert($signal instanceof FiCoreDTMFSignal);

        if (($signal instanceof DTMFSignal) && isset($signal->bridged)) {
            return (array_merge($this->app->planProducer->getChannelPayload($signal->channel), [
                'RestApiServer' => $this->getRestServerAdvertisedHost(),
                'DialDigitsMatch' => $signal->tones,
                'DialAction' => 'digits',
                'DialALegUUID' => $signal->channel->uuid,
                'DialBLegUUID' => $signal->bridged,
            ]));
        } else {
            return (array_merge($this->app->planProducer->getChannelPayload($signal->channel), [
                'Digits' => $signal->tones,
            ]));
        }
    }
}
