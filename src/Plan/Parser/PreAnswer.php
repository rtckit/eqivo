<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\Parser;

use React\Promise\PromiseInterface;
use RTCKit\Eqivo\Plan\PreAnswer\Element as PreAnswerElement;
use RTCKit\Eqivo\Plan\RestXmlElement;

use RTCKit\FiCore\Plan\AbstractElement;
use RTCKit\FiCore\Switch\Channel;

class PreAnswer implements ParserInterface
{
    use ParserTrait;

    /** @var string */
    public const ELEMENT_TYPE = 'PreAnswer';

    /** @var bool */
    public const NO_ANSWER = true;

    /** @var list<string> */
    public const NESTABLES = [
        'GetDigits',
        'GetSpeech',
        'Play',
        'Redirect',
        'Speak',
        'SIPTransfer',
        'Wait',
    ];

    public function parse(RestXmlElement $xmlElement, Channel $channel): PromiseInterface
    {
        $element = new PreAnswerElement();
        $element->channel = $channel;

        return $this->app->planProducer->parseElements($xmlElement, $channel)
            ->then(function (array $elements) use ($element) {
                /** @var list<AbstractElement> $elements */
                $element->elements = $elements;

                return $element;
            });
    }
}
