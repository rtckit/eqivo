<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\Parser;

use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use RTCKit\Eqivo\Exception\RestXmlAttributeException;
use RTCKit\Eqivo\Plan\RestXmlElement;
use RTCKit\FiCore\Plan\AbstractElement;
use RTCKit\FiCore\Plan\CaptureTones\Element as CaptureTonesElement;
use RTCKit\FiCore\Plan\Playback\Element as PlaybackElement;
use RTCKit\FiCore\Plan\Silence\Element as SilenceElement;

use RTCKit\FiCore\Plan\Speak\Element as SpeakElement;

use RTCKit\FiCore\Switch\Channel;

class GetDigits implements ParserInterface
{
    use ParserTrait;

    /** @var string */
    public const ELEMENT_TYPE = 'GetDigits';

    /** @var list<string> */
    public const NESTABLES = ['Speak', 'Play', 'Wait'];

    /** @var int */
    public const DEFAULT_MAX_TONES = 99;

    /** @var int */
    public const DEFAULT_TIMEOUT = 5;

    public function parse(RestXmlElement $xmlElement, Channel $channel): PromiseInterface
    {
        $element = new CaptureTonesElement();
        $element->channel = $channel;

        $attributes = $xmlElement->attributes();

        if (isset($attributes->numDigits)) {
            $element->maxTones = (int)$attributes->numDigits;

            if ($element->maxTones > self::DEFAULT_MAX_TONES) {
                $element->maxTones = self::DEFAULT_MAX_TONES;
            } elseif ($element->maxTones < 1) {
                throw new RestXmlAttributeException("GetDigits 'numDigits' must be greater than 0");
            }
        } else {
            $element->maxTones = self::DEFAULT_MAX_TONES;
        }

        if (isset($attributes->timeout)) {
            $element->timeout = (int)$attributes->timeout;

            if ($element->timeout < 1) {
                throw new RestXmlAttributeException("GetDigits 'timeout' must be a positive integer");
            }
        } else {
            $element->timeout = self::DEFAULT_TIMEOUT;
        }

        $element->timeout *= 1000;

        $element->terminators = isset($attributes->finishOnKey) ? (string)$attributes->finishOnKey : '#';
        $element->invalidMedium = isset($attributes->invalidDigitsSound) ? (string)$attributes->invalidDigitsSound : '';
        $element->validTones = isset($attributes->validDigits) ? (string)$attributes->validDigits : '0123456789*#';

        if (isset($attributes->tries)) {
            $element->tries = (int)$attributes->tries;

            if ($element->tries < 1) {
                throw new RestXmlAttributeException("GetDigits 'tries' must be greater than 0");
            }
        } else {
            $element->tries = 1;
        }

        if (isset($attributes->method)) {
            if (!in_array((string)$attributes->method, ['GET', 'POST'])) {
                throw new RestXmlAttributeException("method must be 'GET' or 'POST'");
            }

            $method = (string)$attributes->method;
        } else {
            $method = 'POST';
        }

        if (isset($attributes->action) && filter_var((string)$attributes->action, FILTER_VALIDATE_URL)) {
            $element->sequence = $method . ':' . (string)$attributes->action;
        }

        return $this->app->planProducer->parseElements($xmlElement, $element->channel)
            ->then(function (array $elements) use ($element, $attributes) {
                /** @var list<AbstractElement> $elements */
                $element->media = $this->app->planProducer->buildPlaybackArray(
                    $element->channel,
                    $elements,
                    [PlaybackElement::class, SilenceElement::class, SpeakElement::class]
                );

                if (isset($attributes->playBeep) && ((string)$attributes->playBeep === 'true')) {
                    $element->media[] = 'tone_stream://%(300,200,700)';
                }

                if (!count($element->media)) {
                    $element->media = ['silence_stream://10'];
                }

                return resolve($element);
            });
    }
}
