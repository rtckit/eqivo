<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Config;

use Monolog\Level;
use InvalidArgumentException;

class LegacyConfigFile implements ResolverInterface
{
    public function resolve(Set $config): void
    {
        if (!isset($config->legacyConfigFile)) {
            return;
        }

        if (!file_exists($config->legacyConfigFile) || !is_readable($config->legacyConfigFile)) {
            fwrite(STDERR, 'Cannot open legacy configuration file: ' . $config->legacyConfigFile . PHP_EOL);
            return;
        }

        $ini = file_get_contents($config->legacyConfigFile);

        if ($ini === false) {
            fwrite(STDERR, 'Cannot read legacy configuration file' . PHP_EOL);
            return;
        }

        $ini = preg_replace('/^#/m', ';', $ini);

        if (is_null($ini)) {
            fwrite(STDERR, 'Cannot process legacy configuration file' . PHP_EOL);
            return;
        }

        $legacy = parse_ini_string($ini, true, INI_SCANNER_RAW);

        if (!is_array($legacy)) {
            fwrite(STDERR, 'Cannot parse legacy configuration file' . PHP_EOL);
            return;
        }

        if (isset($legacy['common']) && is_array($legacy['common'])) {
            if (isset($legacy['common']['DEFAULT_HTTP_METHOD']) && is_string($legacy['common']['DEFAULT_HTTP_METHOD'])) {
                $config->defaultHttpMethod = $legacy['common']['DEFAULT_HTTP_METHOD'];
            }

            if (isset($legacy['common']['DEFAULT_ANSWER_URL']) && is_string($legacy['common']['DEFAULT_ANSWER_URL'])) {
                if (!filter_var($legacy['common']['DEFAULT_ANSWER_URL'], FILTER_VALIDATE_URL)) {
                    fwrite(STDERR, 'Malformed DEFAULT_ANSWER_URL (common) line in legacy configuration file' . PHP_EOL);
                } else {
                    $config->defaultAnswerUrl = $legacy['common']['DEFAULT_ANSWER_URL'];
                }
            }

            if (isset($legacy['common']['DEFAULT_HANGUP_URL']) && is_string($legacy['common']['DEFAULT_HANGUP_URL'])) {
                if (!filter_var($legacy['common']['DEFAULT_HANGUP_URL'], FILTER_VALIDATE_URL)) {
                    fwrite(STDERR, 'Malformed DEFAULT_HANGUP_URL (common) line in legacy configuration file' . PHP_EOL);
                } else {
                    $config->defaultHangupUrl = $legacy['common']['DEFAULT_HANGUP_URL'];
                }
            }

            if (isset($legacy['common']['AUTH_ID']) && is_string($legacy['common']['AUTH_ID'])) {
                $config->restAuthId = $legacy['common']['AUTH_ID'];
            }

            if (isset($legacy['common']['AUTH_TOKEN']) && is_string($legacy['common']['AUTH_TOKEN'])) {
                $config->restAuthToken = $legacy['common']['AUTH_TOKEN'];
            }

            if (isset($legacy['common']['EXTRA_FS_VARS']) && is_string($legacy['common']['EXTRA_FS_VARS'])) {
                $config->extraChannelVars = array_filter(
                    array_map('trim', explode(',', $legacy['common']['EXTRA_FS_VARS'])),
                    function (string $value): bool {
                        return strlen($value) > 0;
                    }
                );
            }
        }

        if (isset($legacy['rest_server']) && is_array($legacy['rest_server'])) {
            if (isset($legacy['rest_server']['ALLOWED_IPS']) && is_string($legacy['rest_server']['ALLOWED_IPS'])) {
                $allowed = explode(',', $legacy['rest_server']['ALLOWED_IPS']);
                $errs = $config->setRestAllowedIps($allowed);

                foreach($errs as $err) {
                    fwrite(STDERR, 'Malformed ALLOWED_IPS (rest_server) line in legacy configuration file: ' . $err . PHP_EOL);
                }
            }

            if (isset($legacy['rest_server']['HTTP_ADDRESS']) && is_string($legacy['rest_server']['HTTP_ADDRESS'])) {
                $err = Set::parseSocketAddr($legacy['rest_server']['HTTP_ADDRESS'], $ip, $port);

                if ($err) {
                    fwrite(STDERR, 'Malformed HTTP_ADDRESS (rest_server) line in legacy configuration file' . PHP_EOL);
                    fwrite(STDERR, $err . PHP_EOL);
                } else {
                    assert(!is_null($ip));
                    assert(!is_null($port));

                    $config->restServerBindIp = $ip;
                    $config->restServerBindPort = $port;
                }
            }

            if (isset($legacy['rest_server']['FS_INBOUND_ADDRESS']) && is_string($legacy['rest_server']['FS_INBOUND_ADDRESS'])) {
                $err = Set::parseHostPort($legacy['rest_server']['FS_INBOUND_ADDRESS'], $host, $port);

                if ($err) {
                    fwrite(STDERR, 'Malformed FS_INBOUND_ADDRESS (rest_server) line in legacy configuration file' . PHP_EOL);
                    fwrite(STDERR, $err . PHP_EOL);
                } else {
                    if (!isset($legacy['rest_server']['FS_INBOUND_PASSWORD']) || !is_string($legacy['rest_server']['FS_INBOUND_PASSWORD'])) {
                        fwrite(STDERR, 'Missing FS_INBOUND_PASSWORD (rest_server) line in legacy configuration file' . PHP_EOL);
                    } else {
                        $core = new Core;

                        assert(!is_null($host));
                        assert(!is_null($port));

                        $core->eslHost = $host;
                        $core->eslPort = $port;
                        $core->eslPassword = $legacy['rest_server']['FS_INBOUND_PASSWORD'];

                        $config->cores[] = $core;
                    }
                }
            }

            if (isset($legacy['rest_server']['RECORD_URL']) && is_string($legacy['rest_server']['RECORD_URL'])) {
                if (!filter_var($legacy['rest_server']['RECORD_URL'], FILTER_VALIDATE_URL)) {
                    fwrite(STDERR, 'Malformed RECORD_URL (rest_server) line in legacy configuration file' . PHP_EOL);
                } else {
                    $config->recordUrl = $legacy['rest_server']['RECORD_URL'];
                }
            }

            if (isset($legacy['rest_server']['CALL_HEARTBEAT_URL']) && is_string($legacy['rest_server']['CALL_HEARTBEAT_URL'])) {
                if (!filter_var($legacy['rest_server']['CALL_HEARTBEAT_URL'], FILTER_VALIDATE_URL)) {
                    fwrite(STDERR, 'Malformed CALL_HEARTBEAT_URL (rest_server) line in legacy configuration file' . PHP_EOL);
                } else {
                    $config->callHeartbeatUrl = $legacy['rest_server']['CALL_HEARTBEAT_URL'];
                }
            }

            if (isset($legacy['rest_server']['LOG_LEVEL'])) {
                try {
                    $config->restServerLogLevel = $config->inboundServerLogLevel = Level::fromName($legacy['rest_server']['LOG_LEVEL']);
                } catch (InvalidArgumentException $e) {
                    fwrite(STDERR, 'Malformed LOG_LEVEL (rest_server) line in legacy configuration file' . PHP_EOL);
                    fwrite(STDERR, $e->getMessage() . PHP_EOL);
                }
            }

            if (isset($legacy['rest_server']['LOG_TYPE'])) {
                if ($legacy['rest_server']['LOG_TYPE'] !== 'stdout') {
                    fwrite(STDERR, 'Unknown LOG_TYPE (rest_server) line in legacy configuration file: ' . $legacy['rest_server']['LOG_TYPE'] . PHP_EOL);
                }
            }

            if (isset($legacy['rest_server']['USER']) && is_string($legacy['rest_server']['USER'])) {
                $config->userName = $legacy['rest_server']['USER'];
            }

            if (isset($legacy['rest_server']['GROUP']) && is_string($legacy['rest_server']['GROUP'])) {
                $config->groupName = $legacy['rest_server']['GROUP'];
            }
        }

        if (isset($legacy['outbound_server']) && is_array($legacy['outbound_server'])) {
            if (isset($legacy['outbound_server']['FS_OUTBOUND_ADDRESS']) && is_string($legacy['outbound_server']['FS_OUTBOUND_ADDRESS'])) {
                $err = Set::parseSocketAddr($legacy['outbound_server']['FS_OUTBOUND_ADDRESS'], $ip, $port);

                if ($err) {
                    fwrite(STDERR, 'Malformed FS_OUTBOUND_ADDRESS (outbound_server) line in legacy configuration file' . PHP_EOL);
                    fwrite(STDERR, $err . PHP_EOL);
                } else {
                    assert(!is_null($ip));
                    assert(!is_null($port));

                    $config->outboundServerBindIp = $ip;
                    $config->outboundServerBindPort = $port;

                    if (!isset($config->outboundServerAdvertisedPort)) {
                        $config->outboundServerAdvertisedPort = $port;
                    }
                }
            }

            if (isset($legacy['outbound_server']['LOG_LEVEL'])) {
                try {
                    $config->outboundServerLogLevel = Level::fromName($legacy['outbound_server']['LOG_LEVEL']);
                } catch (InvalidArgumentException $e) {
                    fwrite(STDERR, 'Malformed LOG_LEVEL (outbound_server) line in legacy configuration file' . PHP_EOL);
                    fwrite(STDERR, $e->getMessage() . PHP_EOL);
                }
            }

            if (isset($legacy['outbound_server']['LOG_TYPE'])) {
                if ($legacy['outbound_server']['LOG_TYPE'] !== 'stdout') {
                    fwrite(STDERR, 'Unknown LOG_TYPE (outbound_server) line in legacy configuration file: ' . $legacy['outbound_server']['LOG_TYPE'] . PHP_EOL);
                }
            }

            if (!isset($config->userName) && isset($legacy['rest_server']['USER'])) {
                $config->userName = $legacy['rest_server']['USER'];
            }

            if (!isset($config->groupName) && isset($legacy['rest_server']['GROUP'])) {
                $config->groupName = $legacy['rest_server']['GROUP'];
            }
        }
    }
}
