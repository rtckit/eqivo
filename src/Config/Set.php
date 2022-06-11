<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Config;

use Monolog\Level;

class Set
{
    public const INBOUND_SOCKET_ADDRESS = 'inbound_socket_address';

    /* General settings */
    public bool $daemonize = false;

    public string $userName;

    public string $groupName;

    public string $appPrefix = 'eqivo';

    public string $pidFile = '/tmp/eqivo.pid';

    public string $legacyConfigFile;

    public string $configFile;

    /** @var list<Core> */
    public array $cores = [];

    public string $defaultHttpMethod = 'POST';

    public string $defaultAnswerUrl;

    public string $defaultHangupUrl;

    /** @var array<int, string> */
    public array $extraChannelVars = [];

    public bool $verifyPeer = true;

    public bool $verifyPeerName = true;

    /* REST Server settings */
    public string $restServerBindIp = '0.0.0.0';

    public int $restServerBindPort = 8088;

    public string $restServerAdvertisedHost;

    public int $restServerMaxHandlers = 1024;

    public int $restServerMaxRequestSize = 16384;

    public Level $restServerLogLevel = Level::Debug;

    /** @var list<string> Allowed CIDRs */
    public array $restAllowedIps = [];

    public string $restAuthId;

    public string $restAuthToken;

    public string $recordUrl;

    /* Outbound Server settings */
    public string $outboundServerBindIp = '0.0.0.0';

    public int $outboundServerBindPort = 8084;

    public string $outboundServerAdvertisedIp = '127.0.0.1';

    public int $outboundServerAdvertisedPort;

    public Level $outboundServerLogLevel = Level::Debug;

    /* Inbound Server settings */
    public Level $inboundServerLogLevel = Level::Debug;

    public string $callHeartbeatUrl;

    public static function parseSocketAddr(string $str, ?string &$ip, ?int &$port): ?string
    {
        $ret = self::parseHostPort($str, $ip, $port);

        if ($ret) {
            return $ret;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return 'Malformed address (IP address required)';
        }

        return null;
    }

    public static function parseHostPort(string $str, ?string &$host, ?int &$port): ?string
    {
        $parts = explode(':', $str);

        if (count($parts) !== 2) {
            return 'Malformed address (missing port number)';
        } else {
            $host = trim($parts[0]);
            $port = (int)$parts[1];

            if (!$port || ($port > 65535)) {
                return 'Malformed address (port number out of bounds)';
            }
        }

        return null;
    }

    /**
     * Configure allowed CIDRs off a mixed list of IPs/CIDRs
     *
     * @param array<mixed> $ips Allowed IPs/CIDRs
     *
     * @return list<string> Errors (if any)
     */
    public function setRestAllowedIps(array $ips): array
    {
        $errs = [];

        foreach ($ips as $ip) {
            if (!is_string($ip)) {
                continue;
            }

            $ip = trim($ip);
            $bits = null;

            if (strpos($ip, '/') !== false) {
                $parts = explode('/', $ip);

                if (!isset($parts[1]) || !ctype_digit($parts[1])) {
                    $errs[] = $ip . ' is not a valid CIDR range';

                    continue;
                }

                $ip = $parts[0];
                $bits = $parts[1];
            }

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $this->restAllowedIps[] = $ip . '/' . ($bits ?? '32');

                continue;
            }

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $this->restAllowedIps[] = $ip . '/' . ($bits ?? '128');

                continue;
            }

            $errs[] = $ip . ' is not a valid IP address';
        }

        return $errs;
    }
}
