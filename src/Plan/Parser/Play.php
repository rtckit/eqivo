<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\Parser;

use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use RTCKit\Eqivo\Exception\RestXmlFormatException;
use RTCKit\Eqivo\Plan\RestXmlElement;

use RTCKit\FiCore\Plan\Playback\Element as PlaybackElement;

use RTCKit\FiCore\Switch\Channel;

class Play implements ParserInterface
{
    use ParserTrait;

    /** @var string */
    public const ELEMENT_TYPE = 'Play';

    /** @var int */
    public const MAX_LOOPS = 10000;

    public function parse(RestXmlElement $xmlElement, Channel $channel): PromiseInterface
    {
        $element = new PlaybackElement();
        $element->channel = $channel;

        $attributes = $xmlElement->attributes();

        if (isset($attributes->loop)) {
            $loop = (int)$attributes->loop;

            if ($loop < 0) {
                throw new RestXmlFormatException("Play 'loop' must be a positive integer or 0");
            }

            if (!$loop || ($loop > self::MAX_LOOPS)) {
                $loop = self::MAX_LOOPS;
            }

            $element->loop = $loop;
        } else {
            $element->loop = 1;
        }

        $element->medium = trim((string)$xmlElement);

        if (!strlen($element->medium)) {
            throw new RestXmlFormatException("No File to play set!");
        }

        return resolve($element);
    }
}
