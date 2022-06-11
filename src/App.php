<?php

declare(strict_types=1);

namespace RTCKit\Eqivo;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use React\EventLoop\Loop;
use WyriHaximus\React\PSR3\Stdio\StdioLogger;
use ArrayIterator;
use InfiniteIterator;
use const SIGINT;
use const SIGTERM;

class App
{
    public const VERSION = '0.5.3';

    public Config\Set $config;

    /** @var list<Config\ResolverInterface> */
    protected array $configResolvers = [];

    public StdioLogger $stdioLogger;

    public HttpClientInterface $httpClient;

    public Inbound\AbstractServer $inboundServer;

    public Outbound\AbstractServer $outboundServer;

    public Rest\AbstractServer $restServer;

    /** @var array<string, Core> */
    protected array $cores = [];

    /** @var array<string, Session> */
    protected array $sessions = [];

    /** @var array<string, Conference> */
    protected array $conferences = [];

    /** @var array<string, CallRequest> */
    protected array $callRequests = [];

    /** @var array<string, ScheduledHangup> */
    protected array $scheduledHangups = [];

    /** @var array<string, ScheduledPlay> */
    protected array $scheduledPlays = [];

    /** @var InfiniteIterator<string, Core> */
    protected InfiniteIterator $coreIterator;

    protected Logger $statsLogger;

    public function setConfig(Config\Set $config): void
    {
        $this->config = $config;
    }

    public function addConfigResolver(Config\ResolverInterface $resolver): void
    {
        $this->configResolvers[] = $resolver;
    }

    public function resolveConfig(): void
    {
        foreach ($this->configResolvers as $resolver) {
            $resolver->resolve($this->config);
        }
    }

    public function setHttpClient(HttpClientInterface $httpClient): void
    {
        $this->httpClient = $httpClient;

        $httpClient->setApp($this);
    }

    public function setInboundServer(Inbound\AbstractServer $server): void
    {
        $this->inboundServer = $server;

        $server->setApp($this);
    }

    public function setOutboundServer(Outbound\AbstractServer $server): void
    {
        $this->outboundServer = $server;

        $server->setApp($this);
    }

    public function setRestServer(Rest\AbstractServer $server): void
    {
        $this->restServer = $server;

        $server->setApp($this);
    }

    public function addCore(Core $core): void
    {
        $this->cores[$core->uuid] = $core;
        $core->app = $this;

        $this->buildCoreIterator();
    }

    public function getCore(string $uuid): ?Core
    {
        return isset($this->cores[$uuid]) ? $this->cores[$uuid] : null;
    }

    public function removeCore(string $uuid): void
    {
        if (isset($this->cores[$uuid])) {
            unset($this->cores[$uuid]);
        }

        $this->buildCoreIterator();
    }

    public function allocateCore(): Core
    {
        if (!isset($this->coreIterator)) {
            throw new Exception\CoreException('No cores available');
        }

        $this->coreIterator->next();

        if (!$this->coreIterator->valid()) {
            throw new Exception\CoreException('No cores available');
        }

        $core = $this->coreIterator->current();

        assert($core instanceof Core);

        return $core;
    }

    /**
     * Returns all active FreeSWITCH cores
     *
     * @return array<string, Core>
     */
    public function getAllCores(): array
    {
        return $this->cores;
    }

    protected function buildCoreIterator(): void
    {
        $this->coreIterator = new InfiniteIterator(new ArrayIterator($this->cores));
        $this->coreIterator->rewind();
    }

    public function addSession(Session $session): void
    {
        $this->sessions[$session->uuid] = $session;
    }

    public function getSession(string $uuid): ?Session
    {
        return isset($this->sessions[$uuid]) ? $this->sessions[$uuid] : null;
    }

    public function removeSession(string $uuid): void
    {
        if (isset($this->sessions[$uuid])) {
            unset($this->sessions[$uuid]);
        }
    }

    public function addConference(Conference $conference): void
    {
        $this->conferences[$conference->room] = $conference;
    }

    public function getConference(string $name): ?Conference
    {
        return isset($this->conferences[$name]) ? $this->conferences[$name] : null;
    }

    public function removeConference(string $name): void
    {
        if (isset($this->conferences[$name])) {
            unset($this->conferences[$name]);
        }
    }

    public function addCallRequest(CallRequest $callRequest): void
    {
        $this->callRequests[$callRequest->uuid] = $callRequest;
    }

    public function getCallRequest(string $uuid): ?CallRequest
    {
        return isset($this->callRequests[$uuid]) ? $this->callRequests[$uuid] : null;
    }

    public function removeCallRequest(string $uuid): void
    {
        if (isset($this->callRequests[$uuid])) {
            unset($this->callRequests[$uuid]);
        }
    }

    public function addScheduledHangup(ScheduledHangup $scheduledHangup): void
    {
        $this->scheduledHangups[$scheduledHangup->uuid] = $scheduledHangup;
    }

    public function getScheduledHangup(string $uuid): ?ScheduledHangup
    {
        return isset($this->scheduledHangups[$uuid]) ? $this->scheduledHangups[$uuid] : null;
    }

    public function removeScheduledHangup(string $uuid): void
    {
        if (isset($this->scheduledHangups[$uuid])) {
            unset($this->scheduledHangups[$uuid]);
        }
    }

    public function addScheduledPlay(ScheduledPlay $scheduledPlay): void
    {
        $this->scheduledPlays[$scheduledPlay->uuid] = $scheduledPlay;
    }

    public function getScheduledPlay(string $uuid): ?ScheduledPlay
    {
        return isset($this->scheduledPlays[$uuid]) ? $this->scheduledPlays[$uuid] : null;
    }

    public function removeScheduledPlay(string $uuid): void
    {
        if (isset($this->scheduledPlays[$uuid])) {
            unset($this->scheduledPlays[$uuid]);
        }
    }

    public function enableStatsReporter(float $interval): void
    {
        $this->statsLogger = new Logger('stats');
        $this->statsLogger->pushHandler((new PsrHandler($this->stdioLogger))->setFormatter(new LineFormatter));
        Loop::addPeriodicTimer($interval, [$this, 'statsReporter']);
    }

    public function statsReporter(): void
    {
        $this->statsLogger->debug('Overall resident object instance count', [
            'CallRequest' => CallRequest::$instances,
            'Conference' => Conference::$instances,
            'Job' => Job::$instances,
            'ScheduledHangup' => ScheduledHangup::$instances,
            'ScheduledPlay' => ScheduledPlay::$instances,
            'Session' => Session::$instances,
        ]);

        $this->statsLogger->debug('App object instance count', [
            'CallRequest' => count($this->callRequests),
            'Conference' => count($this->conferences),
            'ScheduledHangup' => count($this->scheduledHangups),
            'ScheduledPlay' => count($this->scheduledPlays),
            'Session' => count($this->sessions),
        ]);

        foreach ($this->cores as $core) {
            $this->statsLogger->debug("Core {$core->uuid} object instance count", $core->gatherStats());
        }
    }

    public function prepare(): void
    {
        if ($this->config->daemonize) {
            $this->daemonize();
        }

        cli_set_process_title('eqivo');

        if (isset($this->config->groupName)) {
            $this->setGroup($this->config->groupName);
        }

        if (isset($this->config->userName)) {
            $this->setUser($this->config->userName);
        }

        $this->writePidFile();
        $this->setupSignalHandlers();

        $this->stdioLogger = StdioLogger::create()->withHideLevel(true);
    }

    public function run(): void
    {
        $this->inboundServer->run();
        $this->outboundServer->run();
        $this->restServer->run();
    }

    public function shutdown(?int $signal = null): void
    {
        if (isset($signal)) {
            switch ($signal) {
                case SIGINT:
                    fwrite(STDOUT, 'Caught SIGINT' . PHP_EOL);
                    break;

                case SIGTERM:
                    fwrite(STDOUT, 'Caught SIGTERM' . PHP_EOL);
                    break;

                default:
                    fwrite(STDOUT, 'Caught signal ' . $signal . PHP_EOL);
                    break;
            }
        }

        fwrite(STDOUT, 'Shutting down ...' . PHP_EOL);

        $this->inboundServer->shutdown();
        $this->outboundServer->shutdown();
        $this->restServer->shutdown();

        Loop::stop();
    }

    protected function daemonize(): void
    {
        if (!function_exists('pcntl_fork')) {
            fwrite(STDERR, 'Cannot daemonize without pcntl extension' . PHP_EOL);
            Loop::stop();
            exit(1);
        }

        if (($pid = pcntl_fork()) < 0) {
            fwrite(STDERR, 'Cannot fork' . PHP_EOL);
            Loop::stop();
            exit(1);
        }

        if ($pid > 0) {
            echo 'eqivo running in background' . PHP_EOL;
            Loop::stop();
            exit(0);
        }
    }

    protected function setUser(string $userName): void
    {
        if (!extension_loaded('posix')) {
            fwrite(STDERR, 'Cannot set user without posix extension' . PHP_EOL);
            Loop::stop();
            exit(1);
        }

        $user = posix_getpwnam($userName);

        if ($user === false) {
            fwrite(STDERR, 'Unknown user: ' . $userName . PHP_EOL);
            Loop::stop();
            exit(1);
        }

        if (!posix_setuid($user['uid'])) {
            fwrite(STDERR, 'Cannot set UID to ' . $user['uid'] . PHP_EOL);
            Loop::stop();
            exit(1);
        }
    }

    protected function setGroup(string $groupName): void
    {
        if (!extension_loaded('posix')) {
            fwrite(STDERR, 'Cannot set group without posix extension' . PHP_EOL);
            Loop::stop();
            exit(1);
        }

        $group = posix_getgrnam($groupName);

        if ($group === false) {
            fwrite(STDERR, 'Unknown group: ' . $groupName . PHP_EOL);
            Loop::stop();
            exit(1);
        }

        if (!posix_setgid($group['gid'])) {
            fwrite(STDERR, 'Cannot set GID to ' . $group['gid'] . PHP_EOL);
            Loop::stop();
            exit(1);
        }
    }

    protected function writePidFile(): void
    {
        $pid = getmypid();

        if ($pid === false) {
            fwrite(STDERR, 'Cannot determine my own PID' . PHP_EOL);
            return;
        }

        $dir = dirname($this->config->pidFile);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                fwrite(STDERR, 'Cannot create PID file parent directory' . PHP_EOL);
                return;
            }
        }

        $fp = fopen($this->config->pidFile, 'w');

        if ($fp === false) {
            fwrite(STDERR, 'Cannot open PID file for writing' . PHP_EOL);
            return;
        }

        fwrite($fp, (string)$pid);
        fclose($fp);
    }

    protected function setupSignalHandlers(): void
    {
        if (defined('SIGINT')) {
            Loop::addSignal(SIGINT, [$this, 'shutdown']);
            Loop::addSignal(SIGTERM, [$this, 'shutdown']);
        }
    }
}
