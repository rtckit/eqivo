<?php

declare(strict_types=1);

namespace RTCKit\Eqivo;

use React\Promise\Deferred;

class Job
{
    public static int $instances = 0;

    public App $app;

    public Core $core;

    public string $uuid;

    public string $command;

    public bool $group = false;

    public CallRequest $callRequest;

    public Deferred $deferred;

    function __construct() {
        self::$instances++;
    }

    function __destruct() {
        self::$instances--;
    }
}
