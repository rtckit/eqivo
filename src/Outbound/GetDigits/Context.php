<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\GetDigits;

use RTCKit\Eqivo\Session;
use RTCKit\Eqivo\Outbound\ContextInterface;

class Context implements ContextInterface
{
    public Session $session;
    public string $action;
    public string $method;
    public int $numDigits;
    public int $timeout;
    public string $finishOnKey;
    public int $tries;
    public bool $playBeep;
    public string $validDigits;
    public string $invalidDigitsSound;

    /** @var list<string> */
    public array $setVars = [];

    /** @var list<string> */
    public array $unsetVars = [];
}
