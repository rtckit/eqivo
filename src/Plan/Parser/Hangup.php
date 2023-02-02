<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\Parser;

use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use RTCKit\Eqivo\Plan\RestXmlElement;
use RTCKit\FiCore\Plan\Hangup\Element as HangupElement;

use RTCKit\FiCore\Switch\{
    Channel,
    HangupCauseEnum,
};

class Hangup implements ParserInterface
{
    use ParserTrait;

    /** @var string */
    public const ELEMENT_TYPE = 'Hangup';

    public function parse(RestXmlElement $xmlElement, Channel $channel): PromiseInterface
    {
        $element = new HangupElement();
        $element->channel = $channel;

        $attributes = $xmlElement->attributes();

        if (isset($attributes->schedule)) {
            $delay = (int)$attributes->schedule;
            $element->delay = ($delay < 1) ? 0 : $delay;
        } else {
            $element->delay = 0;
        }

        if (isset($attributes->reason)) {
            switch ($attributes->reason) {
                case 'rejected':
                    $element->reason = HangupCauseEnum::CALL_REJECTED->value;
                    break;

                case 'busy':
                    $element->reason = HangupCauseEnum::USER_BUSY->value;
                    break;

                default:
                    $element->reason = HangupCauseEnum::NORMAL_CLEARING->value;
                    break;
            }
        } else {
            $element->reason = HangupCauseEnum::NORMAL_CLEARING->value;
        }

        return resolve($element);
    }
}
