<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Inbound;

use RTCKit\Eqivo\App;

use Monolog\Logger;

abstract class AbstractServer
{
    public App $app;

    public ControllerInterface $controller;

    /** @var array<string, Handler\HandlerInterface> */
    public array $handlers = [];

    public Logger $logger;

    abstract public function setApp(App $app): static;

    abstract public function run(): void;

    abstract public function shutdown(): void;
}
