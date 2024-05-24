<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\Parser;

use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use RTCKit\Eqivo\Exception\RestXmlFormatException;
use RTCKit\Eqivo\Plan\RestXmlElement;

use RTCKit\FiCore\Plan\Silence\Element as SilenceElement;

use RTCKit\FiCore\Switch\Channel;

class Wait implements ParserInterface
{
    use ParserTrait;

    /** @var string */
    public const ELEMENT_TYPE = 'Wait';

    /** @var bool */
    public const NO_ANSWER = true;

    public function parse(RestXmlElement $xmlElement, Channel $channel): PromiseInterface
    {
        $element = new SilenceElement();
        $element->channel = $channel;

        $attributes = $xmlElement->attributes();

        if (isset($attributes->length)) {
            $length = (int)$attributes->length;

            if ($length < 1) {
                throw new RestXmlFormatException("Wait 'length' must be a positive integer");
            }

            $element->duration = $length;
        } else {
            $element->duration = 1;
        }

        return resolve($element);
    }
}
