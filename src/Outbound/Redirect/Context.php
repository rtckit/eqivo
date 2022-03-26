<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\Redirect;

use RTCKit\Eqivo\Session;
use RTCKit\Eqivo\Outbound\ContextInterface;

class Context implements ContextInterface
{
    public Session $session;
    public string $url;
    public string $method;
}
