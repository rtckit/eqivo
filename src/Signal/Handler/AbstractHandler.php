<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Signal\Handler;

use RTCKit\FiCore\AbstractApp;
use RTCKit\Eqivo\Config;

use RTCKit\FiCore\Signal\AbstractSignal;

abstract class AbstractHandler
{
    protected AbstractApp $app;

    private string $restServerAdvertisedHost;

    public function setApp(AbstractApp $app): static
    {
        $this->app = $app;

        return $this;
    }

    protected function getRestServerAdvertisedHost(): string
    {
        if (!isset($this->restServerAdvertisedHost)) {
            assert($this->app->config instanceof Config\Set);

            $this->restServerAdvertisedHost = $this->app->config->restServerAdvertisedHost;
        }

        return $this->restServerAdvertisedHost;
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function export(AbstractSignal $signal): array;
}
