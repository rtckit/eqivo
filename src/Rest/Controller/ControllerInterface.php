<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller;

use RTCKit\Eqivo\App;

use React\Promise\PromiseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface ControllerInterface
{
    public function __construct();

    public function setApp(App $app): static;

    public function execute(ServerRequestInterface $request): PromiseInterface;
}
