<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Signal\Handler\Channel;

use RTCKit\Eqivo\Signal\Handler\AbstractHandler;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Signal\Channel\Progress as ProgressSignal;

class Progress extends AbstractHandler
{
    public function export(AbstractSignal $signal): array
    {
        assert($signal instanceof ProgressSignal);

        $calledNum = '';
        $calledNumVar = "variable_{$this->app->config->appPrefix}_destination_number";
        $reqUuidVar = "variable_{$this->app->config->appPrefix}_request_uuid";

        if (isset($signal->event->{$calledNumVar}) && ($signal->event->{$calledNumVar} !== '_undef_')) {
            $calledNum = $signal->event->{$calledNumVar};
        } elseif (isset($signal->event->{'Caller-Destination-Number'})) {
            $calledNum = $signal->event->{'Caller-Destination-Number'};
        }

        $calledNum = ltrim($calledNum, '+');
        $callerNum = '';

        if (isset($signal->event->{'Caller-Caller-ID-Number'})) {
            $callerNum = ltrim($signal->event->{'Caller-Caller-ID-Number'}, '+');
        }

        $callUuid = isset($signal->event->{'Unique-ID'}) ? $signal->event->{'Unique-ID'} : '';

        $this->app->signalProducer->logger->debug("Call from {$callerNum} to {$calledNum} Ringing for RequestUUID {$signal->event->{$reqUuidVar}}");

        return [
            'RestApiServer' => $this->getRestServerAdvertisedHost(),
            'To' => $calledNum,
            'RequestUUID' => $signal->event->{$reqUuidVar},
            'Direction' => $signal->event->{'Call-Direction'},
            'CallStatus' => $signal->status->value,
            'From' => $callerNum,
            'CallUUID' => $callUuid,
            'CoreUUID' => $signal->event->{'Core-UUID'},
        ];
    }
}
