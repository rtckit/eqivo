<?php
/**
 * RTCKit\Eqivo\Signal\Producer Class
 */
declare(strict_types=1);

namespace RTCKit\Eqivo\Signal;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use React\Http\Message\ResponseException;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use RTCKit\FiCore\AbstractApp;
use RTCKit\Eqivo\App;
use RTCKit\FiCore\Signal\{
    AbstractProducer,
    AbstractSignal,
    ChannelSignal,
};

use Throwable;

/**
 * Eqivo Signal Producer
 */
class Producer extends AbstractProducer
{
    protected AbstractApp $app;

    /** @var array<string, Handler\AbstractHandler> */
    protected array $handlers;

    public function setApp(AbstractApp $app): static
    {
        $this->app = $app;

        return $this;
    }

    public function setSignalHandler(string $signalClass, Handler\AbstractHandler $handler): static
    {
        $this->handlers[$signalClass] = $handler;

        $this->handlers[$signalClass]->setApp($this->app);

        return $this;
    }

    public function run(): void
    {
        $this->logger = new Logger('signal.producer');
        $this->logger->pushHandler(
            (new PsrHandler($this->app->stdioLogger, $this->app->config->signalProducerLogLevel))->setFormatter(new LineFormatter())
        );
        $this->logger->debug('Starting ...');
    }

    /**
     * Exports a signal
     *
     * @param AbstractSignal $signal
     *
     * @return null|array<string, mixed>
     */
    public function export(AbstractSignal $signal): ?array
    {
        if (isset($this->handlers[$signal::class])) {
            return $this->handlers[$signal::class]->export($signal);
        }

        return null;
    }

    /**
     * Emits a signal
     *
     * @param AbstractSignal $signal
     *
     * @return PromiseInterface
     */
    public function produce(AbstractSignal $signal): PromiseInterface
    {
        $payload = $this->export($signal);

        if (!isset($payload) || !isset($signal->attn)) {
            return resolve();
        }

        $parts = explode(':', $signal->attn, 2);

        if (count($parts) !== 2) {
            return resolve();
        }

        $method = $parts[0];
        $url = $parts[1];

        if (empty($method) || empty($url)) {
            return resolve();
        }

        if (isset($signal->event)) {
            foreach ($this->app->config->extraChannelVars as $var) {
                if (isset($signal->event->{$var})) {
                    $payload[$var] = $signal->event->{$var};
                }
            }
        }

        assert($this->app instanceof App);

        return $this->app->httpClient->makeRequest($url, $method, $payload)
            ->then(function () use ($method, $url, $payload): PromiseInterface {
                $this->logger->info("dial {$method} {$url}", $payload);

                return resolve();
            })
            ->otherwise(function (ResponseException $e) use ($method, $url, $payload) {
                $this->logger->debug("dial {$method} {$url} failed: " . $e->getMessage(), $payload);
            })
            ->otherwise(function (Throwable $t) use ($method, $url, $payload) {
                $t = $t->getPrevious() ?: $t;
                $data = [
                    'payload' => $payload,
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ];

                $this->logger->error("dial {$method} {$url} failed unexpectedly: " . $t->getMessage(), $data);
            });
    }
}
