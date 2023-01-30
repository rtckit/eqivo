<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Signal\Handler\Conference;

use RTCKit\Eqivo\Signal\Handler\AbstractHandler;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Signal\Conference\DTMF as DTMFSignal;

class DTMF extends AbstractHandler
{
    public function export(AbstractSignal $signal): array
    {
        assert($signal instanceof DTMFSignal);

        return (array_merge($this->app->planProducer->getChannelPayload($signal->channel), [
            'RestApiServer' => $this->getRestServerAdvertisedHost(),
            'ConferenceAction' => 'digits',
            'ConferenceName' => $signal->conference->room,
            'ConferenceUUID' => $signal->conference->uuid,
            'ConferenceMemberID' => $signal->member,
            'ConferenceDigitsMatch' => $signal->tones,
        ]));
    }
}
