<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller;

use Psr\Http\Message\ServerRequestInterface;

use React\Promise\PromiseInterface;
use RTCKit\Eqivo\App;

interface ControllerInterface
{
    public function __construct();

    public function setApp(App $app): static;

    public function execute(ServerRequestInterface $request): PromiseInterface;
}
