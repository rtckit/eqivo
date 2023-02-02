<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response;

use RTCKit\FiCore\Command\ResponseInterface;

abstract class AbstractResponse
{
    abstract public function import(ResponseInterface $response): static;
}
