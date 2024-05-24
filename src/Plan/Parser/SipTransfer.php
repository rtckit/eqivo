<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\Parser;

use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use RTCKit\Eqivo\Exception\RestXmlFormatException;
use RTCKit\Eqivo\Plan\RestXmlElement;

use RTCKit\Eqivo\Plan\SipTransfer\Element as SipTransferElement;
use RTCKit\FiCore\Switch\Channel;

use RTCKit\SIP;

class SipTransfer implements ParserInterface
{
    use ParserTrait;

    /** @var string */
    public const ELEMENT_TYPE = 'SIPTransfer';

    /** @var bool */
    public const NO_ANSWER = true;

    /** @var list<string> */
    public const ALLOWED_SCHEMES = ['sip', 'sips'];

    public function parse(RestXmlElement $xmlElement, Channel $channel): PromiseInterface
    {
        $element = new SipTransferElement();
        $element->channel = $channel;

        $uris = explode(',', trim((string)$xmlElement));

        foreach ($uris as $uri) {
            try {
                $parsed = SIP\URI::parse($uri);

                if (isset($parsed->scheme) && in_array($parsed->scheme, self::ALLOWED_SCHEMES)) {
                    $element->uris[] = $parsed->render();
                }
            } catch (SIP\Exception\InvalidURIException $e) {
                $this->app->planConsumer->logger->error("Invalid SIP URI: {$uri}");
            }
        }

        if (!isset($element->uris[0])) {
            throw new RestXmlFormatException('SIPTransfer must have a sip uri');
        }

        return resolve($element);
    }
}
