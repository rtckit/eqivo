<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Config;

use InvalidArgumentException;

use Monolog\Logger;
use RTCKit\FiCore\Config\{
    AbstractSet,
    Core,
    ResolverInterface,
};

class EnvironmentVars implements ResolverInterface
{
    /** @var string */
    public const PREFIX = 'EQIVO_';

    public function resolve(AbstractSet $config): void
    {
        assert($config instanceof Set);

        $env = getenv();

        if (isset($env[self::PREFIX . 'DAEMONIZE'])) {
            $config->daemonize = trim($env[self::PREFIX . 'DAEMONIZE']) === 'true';
        }

        if (isset($env[self::PREFIX . 'USER_NAME'])) {
            $config->userName = trim($env[self::PREFIX . 'USER_NAME']);
        }

        if (isset($env[self::PREFIX . 'GROUP_NAME'])) {
            $config->groupName = trim($env[self::PREFIX . 'GROUP_NAME']);
        }

        if (isset($env[self::PREFIX . 'APP_PREFIX'])) {
            $config->appPrefix = trim($env[self::PREFIX . 'APP_PREFIX']);
        }

        if (isset($env[self::PREFIX . 'PID_FILE'])) {
            $config->pidFile = trim($env[self::PREFIX . 'PID_FILE']);
        }

        foreach ($env as $key => $value) {
            if (strpos($key, self::PREFIX . 'CORE_') === 0) {
                $core = Core::parseSpec($value);

                if ($core) {
                    $config->cores[] = $core;
                } else {
                    fwrite(STDERR, 'Cannot core spec: ' . $value . PHP_EOL);
                }
            }
        }

        if (isset($env[self::PREFIX . 'DEFAULT_HTTP_METHOD'])) {
            $config->defaultHttpMethod = trim($env[self::PREFIX . 'DEFAULT_HTTP_METHOD']);
        }

        if (isset($env[self::PREFIX . 'DEFAULT_ANSWER_URL'])) {
            if (!filter_var($env[self::PREFIX . 'DEFAULT_ANSWER_URL'], FILTER_VALIDATE_URL)) {
                fwrite(STDERR, 'Malformed ' . self::PREFIX . 'DEFAULT_ANSWER_URL environment variable' . PHP_EOL);
            } else {
                $config->defaultAnswerUrl = $env[self::PREFIX . 'DEFAULT_ANSWER_URL'];
                $config->defaultAnswerSequence = "{$config->defaultHttpMethod}:{$config->defaultAnswerUrl}";
            }
        }

        if (isset($env[self::PREFIX . 'DEFAULT_HANGUP_URL'])) {
            if (!filter_var($env[self::PREFIX . 'DEFAULT_HANGUP_URL'], FILTER_VALIDATE_URL)) {
                fwrite(STDERR, 'Malformed ' . self::PREFIX . 'DEFAULT_HANGUP_URL environment variable' . PHP_EOL);
            } else {
                $config->defaultHangupSequence = "{$config->defaultHttpMethod}:{$env[self::PREFIX . 'DEFAULT_HANGUP_URL']}";
            }
        }

        if (isset($env[self::PREFIX . 'EXTRA_CHANNEL_VARS'])) {
            $config->extraChannelVars = array_filter(
                array_map('trim', explode(',', $env[self::PREFIX . 'EXTRA_CHANNEL_VARS'])),
                function (string $value): bool {
                    return strlen($value) > 0;
                }
            );
        }

        if (isset($env[self::PREFIX . 'VERIFY_PEER'])) {
            $config->verifyPeer = trim($env[self::PREFIX . 'VERIFY_PEER']) !== 'false';
        }

        if (isset($env[self::PREFIX . 'VERIFY_PEER_NAME'])) {
            $config->verifyPeerName = trim($env[self::PREFIX . 'VERIFY_PEER_NAME']) !== 'false';
        }

        if (isset($env[self::PREFIX . 'REST_BIND_ADDRESS'])) {
            $err = Set::parseSocketAddr($env[self::PREFIX . 'REST_BIND_ADDRESS'], $ip, $port);

            if (is_string($err)) {
                fwrite(STDERR, 'Malformed ' . self::PREFIX . 'REST_BIND_ADDRESS environment variable' . PHP_EOL);
                fwrite(STDERR, $err . PHP_EOL);
            } else {
                assert(!is_null($ip));
                assert(!is_null($port));

                $config->restServerBindIp = $ip;
                $config->restServerBindPort = $port;
            }
        }

        if (isset($env[self::PREFIX . 'REST_ADVERTISED_HOST'])) {
            $config->restServerAdvertisedHost = trim($env[self::PREFIX . 'REST_ADVERTISED_HOST']);
        }

        if (isset($env[self::PREFIX . 'REST_MAX_HANDLERS'])) {
            $value = (int)$env[self::PREFIX . 'REST_MAX_HANDLERS'];

            if ($value > 0) {
                $config->restServerMaxHandlers = $value;
            } else {
                fwrite(STDERR, 'Malformed ' . self::PREFIX . 'REST_MAX_HANDLERS environment variable: must be positive integer' . PHP_EOL);
            }
        }

        if (isset($env[self::PREFIX . 'REST_MAX_REQUEST_SIZE'])) {
            $value = (int)$env[self::PREFIX . 'REST_MAX_REQUEST_SIZE'];

            if ($value > 0) {
                $config->restServerMaxRequestSize = $value;
            } else {
                fwrite(STDERR, 'Malformed ' . self::PREFIX . 'REST_MAX_REQUEST_SIZE environment variable: must be positive integer' . PHP_EOL);
            }
        }

        if (isset($env[self::PREFIX . 'REST_LOG_LEVEL'])) {
            try {
                /**
                 * Allow Monolog to deal with the verbosity level matching
                 * @psalm-suppress ArgumentTypeCoercion
                 * @phpstan-ignore-next-line
                 */
                $config->restServerLogLevel = Logger::toMonologLevel(trim($env[self::PREFIX . 'REST_LOG_LEVEL']));
            } catch (InvalidArgumentException $e) {
                fwrite(STDERR, 'Malformed ' . self::PREFIX . 'REST_LOG_LEVEL environment variable: ' . $e->getMessage() . PHP_EOL);
            }
        }

        if (isset($env[self::PREFIX . 'REST_ALLOWED_IPS'])) {
            $allowed = explode(',', $env[self::PREFIX . 'REST_ALLOWED_IPS']);
            $errs = $config->setRestAllowedIps($allowed);

            foreach ($errs as $err) {
                fwrite(STDERR, 'Malformed ' . self::PREFIX . 'REST_ALLOWED_IPS environment variable: ' . $err . PHP_EOL);
            }
        }

        if (isset($env[self::PREFIX . 'REST_AUTH_ID'])) {
            $config->restAuthId = trim($env[self::PREFIX . 'REST_AUTH_ID']);
        }

        if (isset($env[self::PREFIX . 'REST_AUTH_TOKEN'])) {
            $config->restAuthToken = trim($env[self::PREFIX . 'REST_AUTH_TOKEN']);
        }

        if (isset($env[self::PREFIX . 'RECORD_URL'])) {
            if (!filter_var($env[self::PREFIX . 'RECORD_URL'], FILTER_VALIDATE_URL)) {
                fwrite(STDERR, 'Malformed ' . self::PREFIX . 'RECORD_URL environment variable' . PHP_EOL);
            } else {
                $config->recordingAttn = "{$config->defaultHttpMethod}:{$env[self::PREFIX . 'RECORD_URL']}";
            }
        }

        if (isset($env[self::PREFIX . 'OUTBOUND_BIND_ADDRESS'])) {
            $err = Set::parseSocketAddr($env[self::PREFIX . 'OUTBOUND_BIND_ADDRESS'], $ip, $port);

            if (is_string($err)) {
                fwrite(STDERR, 'Malformed ' . self::PREFIX . 'OUTBOUND_BIND_ADDRESS environment variable' . PHP_EOL);
                fwrite(STDERR, $err . PHP_EOL);
            } else {
                assert(!is_null($ip));
                assert(!is_null($port));

                $config->eslServerBindIp = $ip;
                $config->eslServerBindPort = $port;
            }
        }

        if (isset($env[self::PREFIX . 'OUTBOUND_ADVERTISED_ADDRESS'])) {
            if ($env[self::PREFIX . 'OUTBOUND_ADVERTISED_ADDRESS'] === Set::INBOUND_SOCKET_ADDRESS) {
                $config->eslServerAdvertisedIp = Set::INBOUND_SOCKET_ADDRESS;
            } else {
                $err = Set::parseSocketAddr($env[self::PREFIX . 'OUTBOUND_ADVERTISED_ADDRESS'], $ip, $port);

                if (is_string($err)) {
                    fwrite(STDERR, 'Malformed ' . self::PREFIX . 'OUTBOUND_ADVERTISED_ADDRESS environment variable' . PHP_EOL);
                    fwrite(STDERR, $err . PHP_EOL);
                } else {
                    assert(!is_null($ip));
                    assert(!is_null($port));

                    $config->eslServerAdvertisedIp = $ip;
                    $config->eslServerAdvertisedPort = $port;
                }
            }
        }

        if (isset($env[self::PREFIX . 'OUTBOUND_LOG_LEVEL'])) {
            try {
                /**
                 * Allow Monolog to deal with the verbosity level matching
                 * @psalm-suppress ArgumentTypeCoercion
                 * @phpstan-ignore-next-line
                 */
                $config->eslServerLogLevel = Logger::toMonologLevel(trim($env[self::PREFIX . 'OUTBOUND_LOG_LEVEL']));
            } catch (InvalidArgumentException $e) {
                fwrite(STDERR, 'Malformed ' . self::PREFIX . 'OUTBOUND_LOG_LEVEL environment variable: ' . $e->getMessage() . PHP_EOL);
            }
        }

        if (isset($env[self::PREFIX . 'INBOUND_LOG_LEVEL'])) {
            try {
                /**
                 * Allow Monolog to deal with the verbosity level matching
                 * @psalm-suppress ArgumentTypeCoercion
                 * @phpstan-ignore-next-line
                 */
                $config->eslClientLogLevel = Logger::toMonologLevel(trim($env[self::PREFIX . 'INBOUND_LOG_LEVEL']));
            } catch (InvalidArgumentException $e) {
                fwrite(STDERR, 'Malformed ' . self::PREFIX . 'INBOUND_LOG_LEVEL environment variable: ' . $e->getMessage() . PHP_EOL);
            }
        }

        if (isset($env[self::PREFIX . 'CALL_HEARTBEAT_URL'])) {
            if (!filter_var($env[self::PREFIX . 'CALL_HEARTBEAT_URL'], FILTER_VALIDATE_URL)) {
                fwrite(STDERR, 'Malformed ' . self::PREFIX . 'CALL_HEARTBEAT_URL environment variable' . PHP_EOL);
            } else {
                $config->heartbeatAttn = "{$config->defaultHttpMethod}:{$env[self::PREFIX . 'CALL_HEARTBEAT_URL']}";
            }
        }
    }
}
