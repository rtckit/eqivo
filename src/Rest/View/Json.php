<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\View;

use RTCKit\Eqivo\App;
use RTCKit\Eqivo\Rest\Response\Error as ErrorResponse;

use React\Http\Message\Response;

class Json implements ViewInterface
{
    /** @var array<string, string> */
    private array $headers = [];

    public function __construct()
    {
        $this->headers = [
            'Content-type' => 'application/json',
            'Server' => 'eqivo/v' . App::VERSION,
        ];
    }

    public function execute(object $response): Response
    {
        $body = json_encode($response);

        if ($body === false) {
            return new Response(500, ['Content-Type' => 'text/plain'], ErrorResponse::DEFAULT_BODY[0]);
        }

        return new Response(Response::STATUS_OK, $this->headers, $body);
    }
}
