<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Config;

use Monolog\Logger;
use InvalidArgumentException;

class CliArguments implements ResolverInterface
{
    public function resolve(Set $config): void
    {
        if (!isset($_SERVER['argv'][1])) {
            return;
        }

        $args = getopt(
            'hc:fdp:',
            [
                'help',
                'config:',
                'foreground',
                'daemon',
                'config:',
                'pidfile:',
                'user:',
                'group:',
                'app-prefix:',
                'core:',
                'default-http-method:',
                'default-answer-url:',
                'default-hangup-url:',
                'extra-channel-vars:',
                'verify-peer:',
                'verify-peer-name:',
                'rest-bind-address:',
                'rest-advertised-host:',
                'rest-max-handler:',
                'rest-max-request-size:',
                'rest-log-level:',
                'rest-allowed-ips:',
                'rest-auth-id:',
                'rest-auth-token:',
                'record-url:',
                'outbound-bind-address:',
                'outbound-advertised-address:',
                'outbound-log-level:',
                'inbound-log-level:',
                'call-heartbeat-url:',
            ]
        );

        if (isset($args['help']) || isset($args['h'])) {
            $this->help();
        }

        $configFile = null;

        if (isset($args['config']) && is_string($args['config'])) {
            $configFile = $args['config'];
        } else if (isset($args['c']) && is_string($args['c'])) {
            $configFile = $args['c'];
        }

        if ($configFile) {
            switch (pathinfo($configFile, PATHINFO_EXTENSION)) {
                case 'json':
                case 'yml':
                case 'yaml':
                    $config->configFile = $configFile;
                    break;

                default:
                    $config->legacyConfigFile = $configFile;
                    break;
            }
        }

        if (isset($args['foreground']) || isset($args['f'])) {
            $config->daemonize = false;
        }

        if (isset($args['daemon']) || isset($args['d'])) {
            $config->daemonize = true;
        }

        if (isset($args['pidfile']) && is_string($args['pidfile'])) {
            $config->pidFile = $args['pidfile'];
        } else if (isset($args['p']) && is_string($args['p'])) {
            $config->pidFile = $args['p'];
        }

        if (isset($args['user']) && is_string($args['user'])) {
            $config->userName = $args['user'];
        }

        if (isset($args['group']) && is_string($args['group'])) {
            $config->groupName = $args['group'];
        }

        if (isset($args['app-prefix']) && is_string($args['app-prefix'])) {
            $config->appPrefix = $args['app-prefix'];
        }

        if (isset($args['core'])) {
            if (is_string($args['core'])) {
                $args['core'] = [$args['core']];
            }

            assert(is_array($args['core']));

            foreach ($args['core'] as $spec) {
                assert(is_string($spec));

                $core = Core::parseSpec($spec);

                if ($core) {
                    $config->cores[] = $core;
                } else {
                    fwrite(STDERR, 'Cannot core spec: ' . $spec . PHP_EOL);
                }
            }
        }

        if (isset($args['default-http-method']) && is_string($args['default-http-method'])) {
            $config->defaultHttpMethod = $args['default-http-method'];
        }

        if (isset($args['default-answer-url']) && is_string($args['default-answer-url'])) {
            if (!filter_var($args['default-answer-url'], FILTER_VALIDATE_URL)) {
                fwrite(STDERR, 'Malformed --default-answer-url argument' . PHP_EOL);
            } else {
                $config->defaultAnswerUrl = $args['default-answer-url'];
            }
        }

        if (isset($args['default-hangup-url']) && is_string($args['default-hangup-url'])) {
            if (!filter_var($args['default-hangup-url'], FILTER_VALIDATE_URL)) {
                fwrite(STDERR, 'Malformed --default-hangup-url argument' . PHP_EOL);
            } else {
                $config->defaultHangupUrl = $args['default-hangup-url'];
            }
        }

        if (isset($args['extra-channel-vars']) && is_string($args['extra-channel-vars'])) {
            $config->extraChannelVars = array_filter(
                array_map('trim', explode(',', $args['extra-channel-vars'])),
                function (string $value): bool {
                    return strlen($value) > 0;
                }
            );
        }

        if (isset($args['verify-peer']) && is_string($args['verify-peer'])) {
            $config->verifyPeer = $args['verify-peer'] !== 'false';
        }

        if (isset($args['verify-peer-name']) && is_string($args['verify-peer-name'])) {
            $config->verifyPeerName = $args['verify-peer-name'] !== 'false';
        }

        if (isset($args['rest-bind-address']) && is_string($args['rest-bind-address'])) {
            $err = Set::parseSocketAddr($args['rest-bind-address'], $ip, $port);

            if ($err) {
                fwrite(STDERR, 'Malformed --rest-bind-address argument: ip:port required' . PHP_EOL);
                fwrite(STDERR, $err . PHP_EOL);
            } else {
                assert(!is_null($ip));
                assert(!is_null($port));

                $config->restServerBindIp = $ip;
                $config->restServerBindPort = $port;
            }
        }

        if (isset($args['rest-advertised-host']) && is_string($args['rest-advertised-host'])) {
            $config->restServerAdvertisedHost = $args['rest-advertised-host'];
        }

        if (isset($args['rest-max-handlers'])) {
            $value = (int)$args['rest-max-handlers'];

            if ($value > 0) {
                $config->restServerMaxHandlers = $value;
            } else {
                fwrite(STDERR, 'Malformed --rest-max-handlers argument: must be positive integer' . PHP_EOL);
            }
        }

        if (isset($args['rest-max-request-size'])) {
            $value = (int)$args['rest-max-request-size'];

            if ($value > 0) {
                $config->restServerMaxRequestSize = $value;
            } else {
                fwrite(STDERR, 'Malformed --rest-max-request-size argument: must be positive integer' . PHP_EOL);
            }
        }

        if (isset($args['rest-log-level']) && is_string($args['rest-log-level'])) {
            try {
                /**
                 * Allow Monolog to deal with the verbosity level matching
                 * @psalm-suppress ArgumentTypeCoercion
                 * @phpstan-ignore-next-line
                 */
                $config->restServerLogLevel = Logger::toMonologLevel($args['rest-log-level']);
            } catch (InvalidArgumentException $e) {
                fwrite(STDERR, 'Malformed --rest-log-level argument: ' . $e->getMessage() . PHP_EOL);
            }
        }

        if (isset($args['rest-allowed-ips']) && is_string($args['rest-allowed-ips'])) {
            $allowed = explode(',', $args['rest-allowed-ips']);
            $errs = $config->setRestAllowedIps($allowed);

            foreach($errs as $err) {
                fwrite(STDERR, 'Malformed --rest-allowed-ips argument: ' . $err . PHP_EOL);
            }
        }

        if (isset($args['rest-auth-id']) && is_string($args['rest-auth-id'])) {
            $config->restAuthId = $args['rest-auth-id'];
        }

        if (isset($args['rest-auth-token']) && is_string($args['rest-auth-token'])) {
            $config->restAuthToken = $args['rest-auth-token'];
        }

        if (isset($args['record-url']) && is_string($args['record-url'])) {
            if (!filter_var($args['record-url'], FILTER_VALIDATE_URL)) {
                fwrite(STDERR, 'Malformed --record-url argument' . PHP_EOL);
            } else {
                $config->recordUrl = $args['record-url'];
            }
        }

        if (isset($args['outbound-bind-address']) && is_string($args['outbound-bind-address'])) {
            $err = Set::parseSocketAddr($args['outbound-bind-address'], $ip, $port);

            if ($err) {
                fwrite(STDERR, 'Malformed --outbound-bind-address argument: ip:port required' . PHP_EOL);
                fwrite(STDERR, $err . PHP_EOL);
            } else {
                assert(!is_null($ip));
                assert(!is_null($port));

                $config->outboundServerBindIp = $ip;
                $config->outboundServerBindPort = $port;
            }
        }

        if (isset($args['outbound-advertised-address']) && is_string($args['outbound-advertised-address'])) {
            $err = Set::parseSocketAddr($args['outbound-advertised-address'], $ip, $port);

            if ($err) {
                fwrite(STDERR, 'Malformed --outbound-advertised-address argument: ip:port required' . PHP_EOL);
                fwrite(STDERR, $err . PHP_EOL);
            } else {
                assert(!is_null($ip));
                assert(!is_null($port));

                $config->outboundServerAdvertisedIp = $ip;
                $config->outboundServerAdvertisedPort = $port;
            }
        }

        if (isset($args['outbound-log-level']) && is_string($args['outbound-log-level'])) {
            try {
                /**
                 * Allow Monolog to deal with the verbosity level matching
                 * @psalm-suppress ArgumentTypeCoercion
                 * @phpstan-ignore-next-line
                 */
                $config->outboundServerLogLevel = Logger::toMonologLevel($args['outbound-log-level']);
            } catch (InvalidArgumentException $e) {
                fwrite(STDERR, 'Malformed --outbound-log-level argument: ' . $e->getMessage() . PHP_EOL);
            }
        }

        if (isset($args['inbound-log-level']) && is_string($args['inbound-log-level'])) {
            try {
                /**
                 * Allow Monolog to deal with the verbosity level matching
                 * @psalm-suppress ArgumentTypeCoercion
                 * @phpstan-ignore-next-line
                 */
                $config->inboundServerLogLevel = Logger::toMonologLevel($args['inbound-log-level']);
            } catch (InvalidArgumentException $e) {
                fwrite(STDERR, 'Malformed --inbound-log-level argument: ' . $e->getMessage() . PHP_EOL);
            }
        }

        if (isset($args['call-heartbeat-url']) && is_string($args['call-heartbeat-url'])) {
            if (!filter_var($args['call-heartbeat-url'], FILTER_VALIDATE_URL)) {
                fwrite(STDERR, 'Malformed --call-heartbeat-url argument' . PHP_EOL);
            } else {
                $config->callHeartbeatUrl = $args['call-heartbeat-url'];
            }
        }
    }

    protected function help(): never
    {
        echo 'Usage: ' . $_SERVER['argv'][0] . ' [options]' . PHP_EOL . PHP_EOL . 'Options:' . PHP_EOL;
        echo <<<EOD
  --help | -h                     show this help message and exit
  --config | -c <FILE>            set config file to FILE
  --foreground | -f               run in foreground (default)
  --daemon | -d                   run as daemon
  --pidfile | -p <PIDFILE>        set pid file to PIDFILE
-------------------------------------------------------------------------------------------
  --user <USER>                   start process as a specific user
  --group <GROUP>                 start process as part of a specific group
  --app-prefix <APP_PREFIX>       set application prefix file to APP_PREFIX (default: eqivo)
  --core <CORE>                   configures a FreeSWITCH ESL core connection; the format is
                                  as follows: [user:]password@host:port
                                    - user, optional, ESL username
                                    - password, required, ESL password
                                    - host, required, ESL host name/IP address
                                    - port, required, ESL port. Typically 8021
  --default-http-method <METHOD>  HTTP used method when requesting RestXML or pushing events
  --default-answer-url <URL>      URL to send RestXML requests to when calls are received
  --default-hangup-url <URL>      URL to send hangup events to
  --extra-channel-vars <VARS>     Additional FreeSWITCH channel variables to be included in
                                  RestXML request and notification payloads
  --verify-peer <BOOL>            Toggle verification of remote certificates (default: true)
  --verify-peer-name <BOOL>       Toggle verification of remote peer names (default: true)
  --rest-bind-address <IP:PORT>   IP and port the REST server will be listening at
  --rest-advertised-host <HOST>   Unique identifier (hostname) identifying this environment
  --rest-max-handlers <INT>       Maximum request handlers for the REST server
  --rest-max-request-size <INT>   Maximum request size the REST server will accept
  --rest-log-level <LEVEL>        REST server logging verbosity, must be one of: debug, info,
                                  notice, warning, error, critical, alert, emergency
  --rest-allowed-ips <LIST>       IP addresses/CIDR ranges allowed to access the REST server
  --rest-auth-id <ID>             User portion of Basic Authentication for the REST server
  --rest-auth-token <TOKEN>       Password of Basic Authentication tuple for the REST server
  --record-url <URL>              URL to send recording notifications to
  --outbound-bind-address         IP and port the Outbound server will be listening at
  --outbound-advertised-address   Outbound ESL IP and port FreeSWITCH connects to
  --outbound-log-level <LEVEL>    Outbound server logging verbosity, must be one of: debug,
                                  info, notice, warning, error, critical, alert, emergency
  --inbound-log-level <LEVEL>     Inbound server logging verbosity, must be one of: debug,
                                  info, notice, warning, error, critical, alert, emergency
  --call-heartbeat-url <URL>      URL to send call heartbeat events to
EOD;
        echo PHP_EOL;

        exit;
    }
}
