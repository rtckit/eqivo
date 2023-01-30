<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest;

use Monolog\Logger;

use RTCKit\Eqivo\{
    App,
    Config,
};
use Wikimedia\IPSet;

abstract class AbstractServer
{
    public IPSet $ipSet;

    public Logger $logger;

    public Config\Set $config;

    abstract public function setApp(App $app): static;

    abstract public function run(): void;

    abstract public function shutdown(): void;
}
