<?php

declare(strict_types=1);

namespace RTCKit\Eqivo;

use Monolog\Level;

use React\Http\Browser;
use React\Promise\PromiseInterface;
use React\Socket\Connector;
use RTCKit\Eqivo\Exception\{
    HttpClientException,
    MethodNotAllowedException
};

class HttpClient implements HttpClientInterface
{
    protected Browser $browser;

    protected App $app;

    protected string $signatureHeader;

    /** @var int */
    public const TIMEOUT = 60;

    public function setApp(App $app): static
    {
        $this->app = $app;
        $this->signatureHeader = 'X-' . strtoupper($this->app->config->appPrefix) . '-SIGNATURE';

        assert($this->app->config instanceof Config\Set);

        $connector = new Connector([
            'tls' => [
                'verify_peer' => $this->app->config->verifyPeer,
                'verify_peer_name' => $this->app->config->verifyPeerName,
            ],
        ]);

        $this->browser = new Browser($connector);

        return $this;
    }

    /**
     * Issues a HTTP request
     *
     * @param string $url
     * @param string $method
     * @param array<mixed, mixed> $params
     * @return PromiseInterface
     */
    public function makeRequest(string $url, string $method = 'POST', array $params = []): PromiseInterface
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new HttpClientException("Cannot send {$url}, no url");
        }

        $parsed = parse_url($url);

        if (($parsed === false) || !isset($parsed['scheme'], $parsed['host'])) {
            throw new HttpClientException("Cannot send {$url}, cannot parse url");
        }

        if (isset($parsed['query']) && strlen($parsed['query'])) {
            parse_str($parsed['query'], $extra);
            $params = array_merge($params, $extra);
        }

        $reqUrl = $url;
        $headers = [
            'User-Agent' => 'eqivo/v' . App::VERSION,
        ];

        switch ($method) {
            case 'POST':
                $headers['Content-type'] = 'application/x-www-form-urlencoded';
                break;

            case 'GET':
                $reqUrl = "{$parsed['scheme']}://{$parsed['host']}";

                if (isset($parsed['port'])) {
                    $reqUrl .= ":{$parsed['port']}";
                }

                if (isset($parsed['path'])) {
                    $reqUrl .= $parsed['path'];
                }

                $reqUrl .= '?' . http_build_query($params);
                break;

            default:
                throw new MethodNotAllowedException();
        }

        $headers[$this->signatureHeader] = $this->calculateSignature($reqUrl, $params);

        if ($this->app->restServer->config->restServerLogLevel === Level::Debug) {
            $curl = "curl --location --request {$method} '{$reqUrl}'";

            foreach ($headers as $key => $value) {
                $curl .= " --header '{$key}: {$value}'";
            }

            if ($method === 'POST') {
                foreach ($params as $key => $value) {
                    if (is_scalar($value)) {
                        $curl .= " --data-urlencode '{$key}={$value}'";
                    }
                }
            }

            $this->app->planProducer->logger->debug('makeRequest() ' . $curl);
        }

        /**
         * @psalm-suppress InvalidArgument
         *
         * Argument 1 of React\Http\Browser::withTimeout expects React\Http\number|bool, but 60 provided :D
         */
        $request = $this->browser
            ->withFollowRedirects(true)
            ->withTimeout(self::TIMEOUT);

        switch ($method) {
            case 'POST':
                $command = $request->post($reqUrl, $headers, http_build_query($params));
                break;

            case 'GET':
                $command = $request->get($reqUrl, $headers);
                break;

            default:
                throw new MethodNotAllowedException();
        }

        return $command;
    }

    /**
     * @param string $url
     * @param array<int|string, mixed> $params
     */
    protected function calculateSignature(string $url, array $params): string
    {
        if (isset($this->app->config->restAuthId, $this->app->config->restAuthToken)) {
            $str = $url;
            ksort($params);

            foreach ($params as $key => $value) {
                $str .= $key . $value;
            }

            return base64_encode(hash_hmac('sha1', $str, $this->app->config->restAuthToken, true));
        }

        return '';
    }
}
