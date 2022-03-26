<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\Speak;

use RTCKit\Eqivo\Session;
use RTCKit\Eqivo\Outbound\ContextInterface;

class Context implements ContextInterface
{
    public Session $session;
    public string $text;
    public int $loop;
    public string $engine;
    public string $language;
    public string $voice;
    public string $type;
    public string $method;
    public int $current;
}
