<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller;

use RTCKit\Eqivo\Rest\Response\Error as ErrorResponse;
use RTCKit\Eqivo\Rest\View\Error as ErrorView;

use React\Promise\PromiseInterface;
use Throwable;
use function React\Promise\resolve;

trait ErrorableTrait
{
    public function exceptionHandler(Throwable $t): PromiseInterface
    {
        $t = $t->getPrevious() ?: $t;
        $this->app->restServer->logger->error('REST controller exception: ' . $t->getMessage(), [
            'file' => $t->getFile(),
            'line' => $t->getLine(),
        ]);

        $response = new ErrorResponse;
        $code = (int)$t->getCode();

        if ($code && isset(ErrorResponse::DEFAULT_BODY[$code])) {
            $response->code = $code;
            $response->body = ErrorResponse::DEFAULT_BODY[$response->code];
        } else {
            $response->code = 500;
            $response->body = ErrorResponse::DEFAULT_BODY[0];
        }

        return resolve((new ErrorView)->execute($response));
    }
}
