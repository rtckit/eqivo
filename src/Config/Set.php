<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Config;

use Monolog\Level;

use RTCKit\FiCore;

class Set extends FiCore\Config\AbstractSet
{
    public string $defaultHttpMethod = 'POST';

    public string $defaultAnswerUrl;

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
