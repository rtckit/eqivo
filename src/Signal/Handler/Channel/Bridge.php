<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Signal\Handler\Channel;

use RTCKit\Eqivo\Signal\Channel\Bridge as BridgeSignal;
use RTCKit\Eqivo\Signal\Handler\AbstractHandler;
use RTCKit\FiCore\Signal\AbstractSignal;

class Bridge extends AbstractHandler
{
    public function export(AbstractSignal $signal): array
    {
        assert($signal instanceof BridgeSignal);

        $ret = array_merge($this->app->planProducer->getChannelPayload($signal->channel), [
            'RestApiServer' => $this->getRestServerAdvertisedHost(),
            'DialALegUUID' => $signal->channel->uuid,
            'DialBLegUUID' => $signal->bridged ?? '',
            'DialBLegStatus' => $signal->status->value,
        ]);

        if (isset($signal->hangupCause)) {
            $ret['DialHangupCause'] = $signal->hangupCause->value;
        }

        if (isset($signal->rang)) {
            $ret['DialRingStatus'] = $signal->rang ? 'true' : 'false';
        }

        return $ret;
    }
}
