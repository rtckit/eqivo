<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest;

use RTCKit\Eqivo\{
    App,
    Config,
    EventEnum
};
use RTCKit\Eqivo\Exception\RestServerException;
use RTCKit\Eqivo\Rest\Response\Error as ErrorResponse;

use FastRoute\{Dispatcher, RouteCollector};
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Middleware\{
    LimitConcurrentRequestsMiddleware,
    RequestBodyBufferMiddleware,
    RequestBodyParserMiddleware,
    StreamingRequestMiddleware
};
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use React\Socket\SocketServer;
use Wikimedia\IPSet;
use Throwable;

use function FastRoute\simpleDispatcher;
use function React\Promise\resolve;

/**
 * @OA\Info(
 *      title="Eqivo API",
 *      description="Eqivo OpenApi Specification",
 *      version="v0.1"
 * )
 *
 * @OA\Tag(
 *      name="Call",
 *      description="API methods responsible for spawning and manipulating individual calls"
 * )
 *
 * @OA\Tag(
 *      name="Conference",
 *      description="API methods responsible for managing conference rooms"
 * )
 */
class Server extends AbstractServer
{
    protected App $app;

    protected SocketServer $socket;

    protected Dispatcher $dispatcher;

    /** @var array<string, array <string, Controller\ControllerInterface>> */
    protected $routes = [
        'GET' => [],
        'POST' => [],
    ];

    public function setApp(App $app): static
    {
        $this->app = $app;
        $this->ipSet = new IPSet($this->app->config->restAllowedIps);

        return $this;
    }

    public function setRouteController(string $method, string $route, Controller\ControllerInterface $controller): Server
    {
        if (!isset($this->routes[$method])) {
            throw new RestServerException("Unsupported HTTP method: {$method}");
        }

        $this->routes[$method][$route] = $controller;

        $controller->setApp($this->app);

        return $this;
    }

    public function run(): void
    {
        $this->logger = new Logger('rest');
        $this->logger->pushHandler(
            (new PsrHandler($this->app->stdioLogger, $this->app->config->restServerLogLevel))->setFormatter(new LineFormatter)
        );
        $this->logger->debug('Starting ...');

        if (!isset($this->app->config->restServerAdvertisedHost)) {
            $hostname = gethostname();

            if (!$hostname) {
                $this->app->config->restServerAdvertisedHost = $this->app->config->appPrefix;
            } else {
                $this->app->config->restServerAdvertisedHost = $hostname;
            }

            $this->logger->notice("restServerAdvertisedHost configuration parameter set to '{$this->app->config->restServerAdvertisedHost}'");
        }

        if (!isset($this->app->config->restAllowedIps[0])) {
            $this->logger->alert("restAllowedIps configuration parameter is empty, the REST server is unusable!");

            return;
        }

        if (!isset($this->app->config->restAuthId, $this->app->config->restAuthId[0])) {
            $this->logger->alert("restAuthId configuration parameter is not set, the REST server is unusable!");

            return;
        }

        if (!isset($this->app->config->restAuthToken, $this->app->config->restAuthToken[0])) {
            $this->logger->alert("restAuthToken configuration parameter is not set, the REST server is unusable!");

            return;
        }

        $this->dispatcher = simpleDispatcher(function (RouteCollector $r) {
            foreach ($this->routes as $method => $routes) {
                foreach ($routes as $route => $controller) {
                    $r->addRoute($method, $route, $controller);
                }
            }
        });

        $server = new HttpServer(
            new StreamingRequestMiddleware,
            new LimitConcurrentRequestsMiddleware($this->app->config->restServerMaxHandlers),
            new RequestBodyBufferMiddleware($this->app->config->restServerMaxRequestSize),
            new RequestBodyParserMiddleware,
            [$this, 'router']
        );

        $this->socket = new SocketServer($this->app->config->restServerBindIp . ':' . $this->app->config->restServerBindPort);

        $server->listen($this->socket);
        $server->on('error', function (Throwable $t) {
            $t = $t->getPrevious() ?: $t;
            $this->logger->error('REST Server exception: ' . $t->getMessage(), [
                'file' => $t->getFile(),
                'line' => $t->getLine(),
            ]);
        });

        $listenAddress = $this->socket->getAddress();

        if (is_null($listenAddress)) {
            throw new \RuntimeException('Cannot retrieve REST Server bind address');
        }

        $this->logger->debug('Listening @ ' . $listenAddress);
    }

    public function router(ServerRequestInterface $request): PromiseInterface
    {
        $now = hrtime(true);

        return $this->dispatch($request)
            ->then(function (Response $response) use ($request, $now) {
                $delta = hrtime(true) - $now;
                $ip = $request->getHeaderLine('x-forwarded-for') ?: $request->getServerParams()['REMOTE_ADDR'];
                $method = $request->getMethod();
                $path = $request->getUri()->getPath();
                $version = $response->getProtocolVersion();
                $code = $response->getStatusCode();
                $size = $response->getBody()->getSize();
                $delta /= 1e9;

                $this->logger->info("{$ip} - - {$method} {$path} HTTP/{$version} {$code} {$size} {$delta}");

                return $response;
            });
    }

    public function dispatch(ServerRequestInterface $request): PromiseInterface
    {
        $method = $request->getMethod();
        $route = $this->dispatcher->dispatch($method, $request->getUri()->getPath());

        switch ($route[0]) {
            case Dispatcher::FOUND:
                return $route[1]->execute($request);

            case Dispatcher::NOT_FOUND:
                return resolve(new Response(404, ['Content-Type' => 'text/plain'], ErrorResponse::DEFAULT_BODY[404]));

            case Dispatcher::METHOD_NOT_ALLOWED:
                return resolve(new Response(405, ['Content-Type' => 'text/plain'], ErrorResponse::DEFAULT_BODY[405]));
        }

        return resolve(new Response(500, ['Content-Type' => 'text/plain'], ErrorResponse::DEFAULT_BODY[0]));
    }

    public function shutdown(): void
    {
        if (isset($this->socket)) {
            $this->socket->close();
        }
    }
}
