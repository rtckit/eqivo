<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Command\Conference\Query;

use RTCKit\FiCore\Command\ResponseInterface;

class Response implements ResponseInterface
{
    public bool $successful;

    /** @var array <string, mixed> */
    public array $rooms = [];
}
