<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use function React\Promise\{
    reject,
    resolve
};
use RTCKit\FiCore\Command\ResponseInterface;
use RTCKit\Eqivo\App;
use RTCKit\Eqivo\Exception\AuthException;

use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;
use RTCKit\Eqivo\Rest\Response\Error as ErrorResponse;
use RTCKit\Eqivo\Rest\View\Error as ErrorView;
use Throwable;

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

    protected function doExecute(ServerRequestInterface $request, AbstractResponse $response, AbstractInquiry $inquiry): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($inquiry, $response): PromiseInterface {
                $this->app->restServer->logger->debug($inquiry::class, (array)$inquiry);
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->app->commandConsumer->consume($inquiry->export())
                    ->then(function (ResponseInterface $commandResponse) use ($response) {
                        $response->import($commandResponse);

                        if (property_exists($response, 'RestApiServer')) {
                            /** @psalm-suppress UndefinedPropertyAssignment */
                            $response->RestApiServer = $this->app->restServer->config->restServerAdvertisedHost;
                        }

                        return $this->view->execute($response);
                    });
            })
            ->otherwise($this->exceptionHandler(...));
    }

    /**
     * Validates API call parameters
     *
     * @param AbstractInquiry $inquiry
     * @param AbstractResponse $response
     */
    public function validate(AbstractInquiry $inquiry, AbstractResponse $response): void
    {
        assert($inquiry instanceof AbstractInquiry);
        assert($response instanceof AbstractResponse);
    }

    protected function authenticate(ServerRequestInterface $request): PromiseInterface
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

    protected function validateCredentials(string $id, string $token): PromiseInterface
    {
        if (
            ($id === $this->app->restServer->config->restAuthId) &&
            ($token === $this->app->restServer->config->restAuthToken)
        ) {
            return resolve();
        } else {
            return reject(new AuthException('Invalid credentials'));
        }
    }

    protected function validateIpAddress(string $ip): PromiseInterface
    {
        if (!$this->app->restServer->ipSet->match($ip)) {
            return reject(new AuthException('IP Auth Failed'));
        }

        return resolve();
    }

    protected function exceptionHandler(Throwable $t): PromiseInterface
    {
        $t = $t->getPrevious() ?: $t;
        $this->app->restServer->logger->error('REST controller exception: ' . $t->getMessage(), [
            'file' => $t->getFile(),
            'line' => $t->getLine(),
        ]);

        $response = new ErrorResponse();
        $code = (int)$t->getCode();

        if ($code && isset(ErrorResponse::DEFAULT_BODY[$code])) {
            $response->code = $code;
            $response->body = ErrorResponse::DEFAULT_BODY[$response->code];
        } else {
            $response->code = 500;
            $response->body = ErrorResponse::DEFAULT_BODY[0];
        }

        return resolve((new ErrorView())->execute($response));
    }
}
