<?php

declare(strict_types=1);

namespace RTCKit\Eqivo;

use React\Promise\PromiseInterface;

interface HttpClientInterface
{
    /**
     * Issues a HTTP request
     *
     * @param string $url
     * @param string $method
     * @param array<mixed, mixed> $params
     * @return PromiseInterface
     */
    public function makeRequest(string $url, string $method = 'POST', array $params = []): PromiseInterface;

    public function setApp(App $app): static;
}
