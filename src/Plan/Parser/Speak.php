<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\Parser;

use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use RTCKit\Eqivo\Exception\RestXmlFormatException;
use RTCKit\Eqivo\Plan\RestXmlElement;

use RTCKit\FiCore\Plan\Speak\Element as SpeakElement;

use RTCKit\FiCore\Switch\Channel;

class Speak implements ParserInterface
{
    use ParserTrait;

    /** @var string */
    public const ELEMENT_TYPE = 'Speak';

    /** @var int */
    public const MAX_LOOPS = 10000;

    /** @var list<string> */
    public const METHODS = [
        'N/A',
        'PRONOUNCED',
        'ITERATED',
        'COUNTED',
        'PRONOUNCED_YEAR'
    ];

    /** @var list<string> */
    public const TYPES = [
        'NUMBER',
        'ITEMS',
        'PERSONS',
        'MESSAGES',
        'CURRENCY',
        'TIME_MEASUREMENT',
        'CURRENT_DATE',
        'CURRENT_TIME',
        'CURRENT_DATE_TIME',
        'TELEPHONE_NUMBER',
        'TELEPHONE_EXTENSION',
        'URL',
        'IP_ADDRESS',
        'EMAIL_ADDRESS',
        'POSTAL_ADDRESS',
        'ACCOUNT_NUMBER',
        'NAME_SPELLED',
        'NAME_PHONETIC',
        'SHORT_DATE_TIME',
    ];

    public function parse(RestXmlElement $xmlElement, Channel $channel): PromiseInterface
    {
        $element = new SpeakElement();
        $element->channel = $channel;

        $attributes = $xmlElement->attributes();

        if (isset($attributes->loop)) {
            $loop = (int)$attributes->loop;

            if ($loop < 0) {
                throw new RestXmlFormatException("Speak 'loop' must be a positive integer or 0");
            }

            if (!$loop || ($loop > self::MAX_LOOPS)) {
                $loop = self::MAX_LOOPS;
            }

            $element->loop = $loop;
        } else {
            $element->loop = 1;
        }

        $element->engine = isset($attributes->engine) ? (string)$attributes->engine : 'flite';
        $element->language = isset($attributes->language) ? (string)$attributes->language : 'en';
        $element->voice = isset($attributes->voice) ? (string)$attributes->voice : 'slt';

        if (isset($attributes->type) && in_array((string)$attributes->type, self::TYPES)) {
            $element->type = (string)$attributes->type;
        }

        if (isset($attributes->method) && in_array((string)$attributes->method, self::METHODS)) {
            $element->method = (string)$attributes->method;
        }

        $element->text = trim((string)$xmlElement);

        return resolve($element);
    }
}
