<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\Wait;

use RTCKit\Eqivo\Session;
use RTCKit\Eqivo\Outbound\ContextInterface;

class Context implements ContextInterface
{
    public Session $session;
    public int $length;
}
