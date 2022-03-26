<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Config;

class Core
{
    public string $uuid;

    public string $eslHost;

    public int $eslPort;

    public string $eslUser;

    public string $eslPassword;

    public int $retries = 0;

    public bool $connected = false;

    public const SPEC_REGEX = '/^(?:([^\:]+)\:)?([a-z0-9]+)@([^:]+):([0-9]+)$/mi';

    public static function parseSpec(string $spec): ?Core
    {
        if (!preg_match_all(self::SPEC_REGEX, $spec, $matches)) {
            return null;
        }

        $ret = new Core;

        if (isset($matches[1][0][0])) {
            $ret->eslUser = $matches[1][0];
        }

        $ret->eslPassword = $matches[2][0];
        $ret->eslHost = $matches[3][0];
        $ret->eslPort = (int)$matches[4][0];

        return $ret;
    }
}
