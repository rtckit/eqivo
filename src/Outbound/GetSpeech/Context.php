<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\GetSpeech;

use RTCKit\Eqivo\Session;
use RTCKit\Eqivo\Outbound\ContextInterface;

use React\EventLoop\TimerInterface;

class Context implements ContextInterface
{
    public Session $session;
    public string $action;
    public string $method;
    public string $engine;
    public int $timeout;
    public bool $playBeep;
    public string $grammar;
    public string $grammarPath;
    public TimerInterface $timer;

    /** @var list<string> */
    public array $setVars = [];

    /** @var list<string> */
    public array $unsetVars = [];
}
