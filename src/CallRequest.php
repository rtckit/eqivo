<?php

declare(strict_types=1);

namespace RTCKit\Eqivo;

class CallRequest
{
    public static int $instances = 0;

    public App $app;

    public Core $core;

    public string $uuid;

    public string $to;

    public string $from;

    public string $accountSid;

    public string $ringUrl;

    public string $hangupUrl;

    /** @var list<string> */
    public array $gateways;

    /** @var list<string> */
    public array $originateStr;

    public Job $job;

    public StatusEnum $status;

    function __construct() {
        self::$instances++;
    }

    function __destruct() {
        self::$instances--;
    }
}
