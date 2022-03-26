<?php

declare(strict_types=1);

namespace RTCKit\Eqivo;

use React\Promise\Deferred;

class ScheduledPlay
{
    public static int $instances = 0;

    public App $app;

    public Core $core;

    public string $uuid;

    public int $timeout;

    function __construct() {
        self::$instances++;
    }

    function __destruct() {
        self::$instances--;
    }
}
