<?php

declare(strict_types=1);

namespace RTCKit\Eqivo;

class Conference
{
    public static int $instances = 0;

    public App $app;

    public Core $core;

    public string $uuid;

    public string $room;

    function __construct() {
        self::$instances++;
    }

    function __destruct() {
        self::$instances--;
    }

    /**
     * Return base Conference payload (for notifications)
     *
     * @return array<string, string>
     */
    public function getPayload(): array
    {
        return [
            'ConferenceName' => $this->room,
        ];
    }
}
