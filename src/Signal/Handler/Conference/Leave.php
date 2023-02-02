<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Signal\Handler\Conference;

use RTCKit\Eqivo\Signal\Handler\AbstractHandler;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Signal\Conference\Leave as LeaveSignal;

class Leave extends AbstractHandler
{
    public function export(AbstractSignal $signal): array
    {
        assert($signal instanceof LeaveSignal);

        $ret = array_merge($this->app->planProducer->getChannelPayload($signal->channel), [
            'RestApiServer' => $this->getRestServerAdvertisedHost(),
            'ConferenceAction' => 'exit',
            'ConferenceName' => $signal->conference->room,
            'ConferenceUUID' => $signal->conference->uuid,
            'ConferenceMemberID' => $signal->member,
        ]);

        if (isset($signal->medium)) {
            $ret['RecordFile'] = $signal->medium;
        }

        return $ret;
    }
}
