<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound;

use RTCKit\Eqivo\App;
use RTCKit\Eqivo\Config;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use RTCKit\React\ESL\{
    RemoteOutboundClient,
    OutboundServer
};

class Server extends AbstractServer
{
    protected App $app;

    protected OutboundServer $server;

    public function setApp(App $app): static
    {
        $this->app = $app;

        return $this;
    }

    public function setController(ControllerInterface $controller): Server
    {
        $this->controller = $controller;

        $this->controller->setApp($this->app);

        return $this;
    }

    public function setElementHandler(HandlerInterface $handler): Server
    {
        $this->handlers[$handler::ELEMENT_TYPE] = $handler;

        $this->handlers[$handler::ELEMENT_TYPE]->setApp($this->app);

        return $this;
    }

    public function run(): void
    {
        $this->logger = new Logger('outbound');
        $this->logger->pushHandler(
            (new PsrHandler($this->app->stdioLogger, $this->app->config->outboundServerLogLevel))->setFormatter(new LineFormatter)
        );
        $this->logger->debug('Starting ...');

        if (!isset($this->app->config->outboundServerAdvertisedPort)) {
            $this->app->config->outboundServerAdvertisedPort = $this->app->config->outboundServerBindPort;

            $this->logger->notice("outboundServerAdvertisedPort configuration parameter set to {$this->app->config->outboundServerAdvertisedPort}");
        }

        if (!isset($this->app->config->defaultAnswerUrl)) {
            $this->logger->alert("defaultAnswerUrl configuration parameter is not set, inbound calls will fail!");
        }

        libxml_use_internal_errors(true);

        $this->server = new OutboundServer($this->app->config->outboundServerBindIp, $this->app->config->outboundServerBindPort);
        $this->server->on('connect', [$this->controller, 'onConnect']);

        $this->server->on('error', function (\Throwable $t) {
            $t = $t->getPrevious() ?: $t;
            $this->logger->error('Outbound Server exception: ' . $t->getMessage());
        });

        $this->server->listen();

        $address = $this->server->getAddress();

        assert(!is_null($address));
        $this->logger->debug('Listening @ ' . $address);
    }

    public function shutdown(): void
    {
        $this->server->close();
    }
}
