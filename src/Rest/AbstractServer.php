<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest;

use RTCKit\Eqivo\App;

use Monolog\Logger;
use Wikimedia\IPSet;

abstract class AbstractServer
{
    public IPSet $ipSet;

    public Logger $logger;

    abstract public function setApp(App $app): static;

    abstract public function run(): void;

    abstract public function shutdown(): void;
}
