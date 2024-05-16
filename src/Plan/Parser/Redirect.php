<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\Parser;

use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use RTCKit\Eqivo\Exception\{
    RestXmlAttributeException,
    RestXmlFormatException,
};
use RTCKit\Eqivo\Plan\RestXmlElement;

use RTCKit\FiCore\Plan\Redirect\Element as RedirectElement;

use RTCKit\FiCore\Switch\Channel;

class Redirect implements ParserInterface
{
    use ParserTrait;

    /** @var string */
    public const ELEMENT_TYPE = 'Redirect';

    /** @var bool */
    public const NO_ANSWER = true;

    public function parse(RestXmlElement $xmlElement, Channel $channel): PromiseInterface
    {
        $element = new RedirectElement();
        $element->channel = $channel;

        $attributes = $xmlElement->attributes();

        if (isset($attributes->method)) {
            if (!in_array((string)$attributes->method, ['GET', 'POST'])) {
                throw new RestXmlAttributeException("Method must be 'GET' or 'POST'");
            }

            $method = (string)$attributes->method;
        } else {
            $method = 'POST';
        }

        $url = trim((string)$xmlElement);

        if (!strlen($url)) {
            throw new RestXmlFormatException("Redirect must have an URL");
        }

        if (!(filter_var($url, FILTER_VALIDATE_URL))) {
            throw new RestXmlFormatException("Redirect URL '{$url}' not valid!");
        }

        $element->sequence = $method . ':' . $url;

        return resolve($element);
    }
}
