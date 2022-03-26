<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\Dial;

use RTCKit\Eqivo\Session;
use RTCKit\Eqivo\Outbound\ContextInterface;

class Context implements ContextInterface
{
    public Session $session;
    public string $action;
    public string $method;
    public bool $hangupOnStar;
    public string $callerId;
    public string $callerName;
    public int $timeLimit;
    public int $timeout;
    public string $confirmSound;
    public string $confirmKey;
    public string $dialMusic;
    public bool $redirect;
    public string $callbackUrl;
    public string $callbackMethod;
    public string $digitsMatch;

    /** @var list<Number> */
    public array $numbers = [];

    public string $dialStr;
    public string $bLegUuid;
    public string $schedHangupId;
    public string $hangupCause;

    /** @var list<string> */
    public array $setVars = [];

    /** @var list<string> */
    public array $unsetVars = [];
}
