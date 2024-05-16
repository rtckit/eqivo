<?php

declare(strict_types=1);

namespace RTCKit\Eqivo;

use RTCKit\Eqivo\Config\Set;

use RTCKit\FiCore\{
    AbstractApp,
    Config,
};

class App extends AbstractApp
{
    /** @var string */
    public const VERSION = '0.6.2';

    public HttpClientInterface $httpClient;

    public Rest\AbstractServer $restServer;

    public function setConfig(Config\AbstractSet $config): static
    {
        assert($config instanceof Set);

        $this->config = $config;

        return $this;
    }

    public function setHttpClient(HttpClientInterface $httpClient): void
    {
        $this->httpClient = $httpClient;

        $httpClient->setApp($this);
    }

    public function setRestServer(Rest\AbstractServer $server): void
    {
        $this->restServer = $server;

        $server->setApp($this);
    }

    public function run(): void
    {
        $this->eslClient->run();
        $this->eslServer->run();
        $this->eventConsumer->run();

        $this->commandConsumer->run();

        $this->planConsumer->run();
        $this->planProducer->run();

        $this->restServer->run();
        $this->signalProducer->run();
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

        $currUsedMemory = memory_get_usage() / 1024 / 1024;
        $peakUsedMemory = memory_get_peak_usage() / 1024 / 1024;

        fwrite(STDOUT, 'Shutting down ...' . PHP_EOL);
        fprintf(STDOUT, "Used memory: %.02f MiB (peak: %0.2f MiB)" . PHP_EOL, $currUsedMemory, $peakUsedMemory);

        $this->eslClient->shutdown();
        $this->eslServer->shutdown();

        $this->restServer->shutdown();

        $this->exit();
    }
}
