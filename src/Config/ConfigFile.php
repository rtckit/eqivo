<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Config;

use RTCKit\Eqivo\Exception\EqivoException;

use Monolog\Logger;
use Symfony\Component\Yaml\Yaml;
use Closure;
use InvalidArgumentException;

/**
 * @phpstan-import-type Level from \Monolog\Logger
 */
class ConfigFile implements ResolverInterface
{
    public function resolve(Set $config): void
    {
        if (!isset($config->configFile)) {
            return;
        }

        if (!file_exists($config->configFile) || !is_readable($config->configFile)) {
            fwrite(STDERR, 'Cannot open configuration file: ' . $config->configFile . PHP_EOL);
            return;
        }

        $body = file_get_contents($config->configFile);

        if ($body === false) {
            fwrite(STDERR, 'Cannot read configuration file' . PHP_EOL);
            return;
        }

        try {
            switch (pathinfo($config->configFile, PATHINFO_EXTENSION)) {
                case 'json':
                    $input = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                    break;

                case 'yml':
                case 'yaml':
                    $input = Yaml::parse($body);
                    break;

                default:
                    throw new EqivoException('Unknown file format');
            }
        } catch (\Throwable $t) {
            fwrite(STDERR, 'Cannot parse configuration file: ' . $t->getMessage() . PHP_EOL);
            return;
        }

        if (!is_array($input)) {
            fwrite(STDERR, 'Cannot parse configuration file: expecting an associative array' . PHP_EOL);
            return;
        }

        if (isset($input['daemonize'])) {
            if (is_bool($input['daemonize'])) {
                $config->daemonize = $input['daemonize'];
            } else {
                fwrite(STDERR, 'Malformed `daemonize` parameter in configuration file: must be boolean value' . PHP_EOL);
            }
        }

        if (isset($input['userName']) && is_string($input['userName'])) {
            $config->userName = trim($input['userName']);
        }

        if (isset($input['groupName']) && is_string($input['groupName'])) {
            $config->groupName = trim($input['groupName']);
        }

        if (isset($input['appPrefix']) && is_string($input['appPrefix'])) {
            $config->appPrefix = trim($input['appPrefix']);
        }

        if (isset($input['pidFile']) && is_string($input['pidFile'])) {
            $config->pidFile = trim($input['pidFile']);
        }

        if (isset($input['cores']) && is_array($input['cores'])) {
            foreach ($input['cores'] as $coreConfig) {
                if (
                    !is_array($coreConfig) || !isset($coreConfig['eslPassword'], $coreConfig['eslHost'], $coreConfig['eslPort']) ||
                    !is_string($coreConfig['eslPassword']) || !is_string($coreConfig['eslHost']) || !is_string($coreConfig['eslPort'])
                ) {
                    fwrite(STDERR, 'Malformed `cores` parameter entry in configuration file: password, host and port are mandatory' . PHP_EOL);

                    continue;
                }

                $core = new Core;

                if (isset($coreConfig['eslUser']) && is_string($coreConfig['eslUser'])) {
                    $core->eslUser = $coreConfig['eslUser'];
                }

                $core->eslPassword = $coreConfig['eslPassword'];
                $core->eslHost = $coreConfig['eslHost'];
                $core->eslPort = (int)$coreConfig['eslPort'];

                $config->cores[] = $core;
            }
        }

        if (isset($input['defaultHttpMethod']) && is_string($input['defaultHttpMethod'])) {
            $config->defaultHttpMethod = trim($input['defaultHttpMethod']);
        }

        if (isset($input['defaultAnswerUrl']) && is_string($input['defaultAnswerUrl'])) {
            if (!filter_var($input['defaultAnswerUrl'], FILTER_VALIDATE_URL)) {
                fwrite(STDERR, 'Malformed `defaultAnswerUrl` parameter in configuration file' . PHP_EOL);
            } else {
                $config->defaultAnswerUrl = $input['defaultAnswerUrl'];
            }
        }

        if (isset($input['defaultHangupUrl']) && is_string($input['defaultHangupUrl'])) {
            if (!filter_var($input['defaultHangupUrl'], FILTER_VALIDATE_URL)) {
                fwrite(STDERR, 'Malformed `defaultHangupUrl` parameter in configuration file' . PHP_EOL);
            } else {
                $config->defaultHangupUrl = $input['defaultHangupUrl'];
            }
        }

        if (isset($input['extraChannelVars']) && is_array($input['extraChannelVars'])) {
            /* array_map() used to work fine here, but now it breaks the static analysis:
             * https://github.com/phpstan/phpstan/issues/4376
             */
            foreach ($input['extraChannelVars'] as $var) {
                if (is_string($var)) {
                    $var = trim($var);

                    if (isset($var[0])) {
                        $config->extraChannelVars[] = $var;
                    }
                }
            }
        }

        if (isset($input['verifyPeer'])) {
            if (is_bool($input['verifyPeer'])) {
                $config->verifyPeer = $input['verifyPeer'];
            } else {
                fwrite(STDERR, 'Malformed `verifyPeer` parameter in configuration file: must be boolean value' . PHP_EOL);
            }
        }

        if (isset($input['verifyPeerName'])) {
            if (is_bool($input['verifyPeerName'])) {
                $config->verifyPeerName = $input['verifyPeerName'];
            } else {
                fwrite(STDERR, 'Malformed `verifyPeerName` parameter in configuration file: must be boolean value' . PHP_EOL);
            }
        }

        if (isset($input['restServerBindIp']) && is_string($input['restServerBindIp'])) {
            if (filter_var($input['restServerBindIp'], FILTER_VALIDATE_IP)) {
                $config->restServerBindIp = $input['restServerBindIp'];
            } else {
                fwrite(STDERR, 'Malformed `restServerBindIp` parameter in configuration file: valid IP address required' . PHP_EOL);
            }
        }

        if (isset($input['restServerBindPort']) && is_scalar($input['restServerBindPort'])) {
            $port = (int)$input['restServerBindPort'];

            if (!$port || ($port > 65535)) {
                fwrite(STDERR, 'Malformed `restServerBindPort` parameter in configuration file: valid port number required' . PHP_EOL);
            } else {
                $config->restServerBindPort = $port;
            }
        }

        if (isset($input['restServerAdvertisedHost']) && is_string($input['restServerAdvertisedHost'])) {
            $config->restServerAdvertisedHost = trim($input['restServerAdvertisedHost']);
        }

        if (isset($input['restServerMaxHandlers'])) {
            if (is_integer($input['restServerMaxHandlers']) && ($input['restServerMaxHandlers'] > 0)) {
                $config->restServerMaxHandlers = $input['restServerMaxHandlers'];
            } else {
                fwrite(STDERR, 'Malformed `restServerMaxHandlers` parameter in configuration file: must be positive integer value' . PHP_EOL);
            }
        }

        if (isset($input['restServerMaxRequestSize'])) {
            if (is_integer($input['restServerMaxRequestSize']) && ($input['restServerMaxRequestSize'] > 0)) {
                $config->restServerMaxRequestSize = $input['restServerMaxRequestSize'];
            } else {
                fwrite(STDERR, 'Malformed `restServerMaxRequestSize` parameter in configuration file: must be positive integer value' . PHP_EOL);
            }
        }

        if (isset($input['restServerLogLevel'])) {
            try {
                /** @var Level */
                $restServerLogLevel = $input['restServerLogLevel'];
                $config->restServerLogLevel = Logger::toMonologLevel($restServerLogLevel);
            } catch (InvalidArgumentException $e) {
                fwrite(STDERR, 'Malformed `restServerLogLevel` parameter in configuration file: ' . $e->getMessage() . PHP_EOL);
            }
        }

        if (isset($input['restAllowedIps'])) {
            if (is_array($input['restAllowedIps'])) {
                $errs = $config->setRestAllowedIps($input['restAllowedIps']);

                foreach($errs as $err) {
                    fwrite(STDERR, 'Malformed `restAllowedIps` parameter in configuration file: ' . $err . PHP_EOL);
                }
            } else {
                fwrite(STDERR, 'Malformed `restAllowedIps` parameter in configuration file: must be an array of IP addresses/CIDR ranges' . PHP_EOL);
            }
        }

        if (isset($input['restAuthId']) && is_string($input['restAuthId'])) {
            $config->restAuthId = trim($input['restAuthId']);
        }

        if (isset($input['restAuthToken']) && is_string($input['restAuthToken'])) {
            $config->restAuthToken = trim($input['restAuthToken']);
        }

        if (isset($input['recordUrl']) && is_string($input['recordUrl'])) {
            if (!filter_var($input['recordUrl'], FILTER_VALIDATE_URL)) {
                fwrite(STDERR, 'Malformed `recordUrl` parameter in configuration file' . PHP_EOL);
            } else {
                $config->recordUrl = $input['recordUrl'];
            }
        }

        if (isset($input['outboundServerBindIp']) && is_string($input['outboundServerBindIp'])) {
            if (filter_var($input['outboundServerBindIp'], FILTER_VALIDATE_IP)) {
                $config->outboundServerBindIp = $input['outboundServerBindIp'];
            } else {
                fwrite(STDERR, 'Malformed `outboundServerBindIp` parameter in configuration file: valid IP address required' . PHP_EOL);
            }
        }

        if (isset($input['outboundServerBindPort']) && is_scalar($input['outboundServerBindPort'])) {
            $port = (int)$input['outboundServerBindPort'];

            if (!$port || ($port > 65535)) {
                fwrite(STDERR, 'Malformed `outboundServerBindPort` parameter in configuration file: valid port number required' . PHP_EOL);
            } else {
                $config->outboundServerBindPort = $port;
            }
        }

        if (isset($input['outboundServerAdvertisedIp']) && is_string($input['outboundServerAdvertisedIp'])) {
            if (filter_var($input['outboundServerAdvertisedIp'], FILTER_VALIDATE_IP)) {
                $config->outboundServerAdvertisedIp = $input['outboundServerAdvertisedIp'];
            } else if ($input['outboundServerAdvertisedIp'] === Set::INBOUND_SOCKET_ADDRESS) {
                $config->outboundServerAdvertisedIp = Set::INBOUND_SOCKET_ADDRESS;
            } else {
                fwrite(STDERR, 'Malformed `outboundServerAdvertisedIp` parameter in configuration file: valid IP address or `' . Set::INBOUND_SOCKET_ADDRESS . '` required' . PHP_EOL);
            }
        }

        if (isset($input['outboundServerAdvertisedPort']) && is_scalar($input['outboundServerAdvertisedPort'])) {
            $port = (int)$input['outboundServerAdvertisedPort'];

            if (!$port || ($port > 65535)) {
                fwrite(STDERR, 'Malformed `outboundServerAdvertisedPort` parameter in configuration file: valid port number required' . PHP_EOL);
            } else {
                $config->outboundServerAdvertisedPort = $port;
            }
        }

        if (isset($input['outboundServerLogLevel'])) {
            try {
                /** @var Level */
                $outboundServerLogLevel = $input['outboundServerLogLevel'];
                $config->outboundServerLogLevel = Logger::toMonologLevel($outboundServerLogLevel);
            } catch (InvalidArgumentException $e) {
                fwrite(STDERR, 'Malformed `outboundServerLogLevel` parameter in configuration file: ' . $e->getMessage() . PHP_EOL);
            }
        }

        if (isset($input['inboundServerLogLevel'])) {
            try {
                /** @var Level */
                $inboundServerLogLevel = $input['inboundServerLogLevel'];
                $config->inboundServerLogLevel = Logger::toMonologLevel($inboundServerLogLevel);
            } catch (InvalidArgumentException $e) {
                fwrite(STDERR, 'Malformed `inboundServerLogLevel` parameter in configuration file: ' . $e->getMessage() . PHP_EOL);
            }
        }

        if (isset($input['callHeartbeatUrl']) && is_string($input['callHeartbeatUrl'])) {
            if (!filter_var($input['callHeartbeatUrl'], FILTER_VALIDATE_URL)) {
                fwrite(STDERR, 'Malformed `callHeartbeatUrl` parameter in configuration file' . PHP_EOL);
            } else {
                $config->callHeartbeatUrl = $input['callHeartbeatUrl'];
            }
        }
    }
}
