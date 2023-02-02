<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\Parser;

use React\Promise\PromiseInterface;
use RTCKit\Eqivo\App;

use RTCKit\Eqivo\Plan\RestXmlElement;
use RTCKit\FiCore\Switch\Channel;

interface ParserInterface
{
    /** @var string */
    public const ELEMENT_TYPE = 'default';

    /** @var list<string> */
    public const NESTABLES = [];

    public function setApp(App $app): static;

    public function parse(RestXmlElement $xmlElement, Channel $channel): PromiseInterface;
}
