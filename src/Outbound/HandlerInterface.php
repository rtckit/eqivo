<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound;

use RTCKit\Eqivo\{
    App,
    Session
};

use React\Promise\PromiseInterface;

interface HandlerInterface
{
    /** @var string */
    public const ELEMENT_TYPE = 'default';

    public const NO_ANSWER = false;

    public const NESTABLES = [];

    public function setApp(App $app): static;

    public function execute(Session $session, RestXmlElement $element): PromiseInterface;

    public function fetchContext(Session $session, RestXmlElement $element): ContextInterface;
}
