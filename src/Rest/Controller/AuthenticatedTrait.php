<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller;

use RTCKit\Eqivo\{
    App,
    Config
};
use RTCKit\Eqivo\Exception\AuthException;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use function React\Promise\{
    resolve,
    reject
};

/**
 * @OA\SecurityScheme(
 *      securityScheme="basicAuth",
 *      type="http",
 *      scheme="basic",
 * )
 */
trait AuthenticatedTrait
{
    protected App $app;

    public function setApp(App $app): static
    {
        $this->app = $app;

        return $this;
    }

    public function validateCredentials(string $id, string $token): PromiseInterface
    {
        if (($id === $this->app->config->restAuthId) && ($token === $this->app->config->restAuthToken)) {
            return resolve();
        } else {
            return reject(new AuthException('Invalid credentials'));
        }
    }

    public function validateIpAddress(string $ip): PromiseInterface
    {
        if (!$this->app->restServer->ipSet->match($ip)) {
            return reject(new AuthException('IP Auth Failed'));
        }

        return resolve();
    }

    public function authenticate(ServerRequestInterface $request): PromiseInterface
    {
        return $this->validateIpAddress($request->getServerParams()['REMOTE_ADDR'])
            ->then(function () use ($request): PromiseInterface {
                $auth = $request->getHeaderLine('Authorization');

                if (empty($auth)) {
                    return reject(new AuthException('Missing authentication header'));
                }

                $parts = explode(' ', $auth, 2);

                if (count($parts) !== 2) {
                    return reject(new AuthException('Malformed authentication header'));
                }

                if (strtolower($parts[0]) !== 'basic') {
                    return reject(new AuthException('Unsupported authentication scheme: ' . $parts[0]));
                }

                $decoded = base64_decode($parts[1], true);

                if (!$decoded) {
                    return reject(new AuthException('Cannot decode authentication payload'));
                }

                $parts = explode(':', $decoded, 2);

                if (count($parts) !== 2) {
                    return reject(new AuthException('Malformed authentication payload'));
                }

                return $this->validateCredentials($parts[0], $parts[1]);
            });
    }
}
