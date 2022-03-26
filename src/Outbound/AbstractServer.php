<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound;

use RTCKit\Eqivo\App;

use Monolog\Logger;

abstract class AbstractServer
{
    /** @var array<string, HandlerInterface> */
    public array $handlers = [];

    public Logger $logger;

    public ControllerInterface $controller;

    abstract public function setApp(App $app): static;

    abstract public function run(): void;

    abstract public function shutdown(): void;
}
