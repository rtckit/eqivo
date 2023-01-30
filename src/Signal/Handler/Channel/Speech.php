<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Signal\Handler\Channel;

use RTCKit\Eqivo\Signal\Handler\AbstractHandler;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Signal\Channel\Speech as SpeechSignal;
use RTCKit\FiCore\Switch\SpeechInterpretation;

class Speech extends AbstractHandler
{
    public function export(AbstractSignal $signal): array
    {
        assert($signal instanceof SpeechSignal);

        usort($signal->interpretations, function (SpeechInterpretation $a, SpeechInterpretation $b) {
            if ($a->confidence === $b->confidence) {
                return 0;
            }

            return ($a->confidence > $b->confidence) ? -1 : 1;
        });

        $confidence = isset($signal->interpretations[0]) ? $signal->interpretations[0]->confidence : -1;

        $ret = array_merge($this->app->planProducer->getChannelPayload($signal->channel), [
            'RestApiServer' => $this->getRestServerAdvertisedHost(),
            'Confidence' => $confidence,
        ]);

        if (isset($signal->interpretations[0]->grammar)) {
            $ret['Grammar'] = $signal->interpretations[0]->grammar;
        }

        if (isset($signal->interpretations[0]->mode)) {
            $ret['Mode'] = $signal->interpretations[0]->mode;
        }

        if (isset($signal->interpretations[0]->input)) {
            $ret['SpeechResult'] = $ret['SpeechInterpretation'] = $signal->interpretations[0]->input;
        }

        return $ret;
    }
}
