<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\View;

use React\Http\Message\Response;
use RTCKit\Eqivo\App;

use RTCKit\Eqivo\Rest\Response\Error as ErrorResponse;

class Error implements ViewInterface
{
    /** @var array<string, string> */
    private array $headers = [];

    public function __construct()
    {
        $this->headers = [
            'Content-type' => 'text/html',
            'Server' => 'ficore/v' . App::VERSION,
        ];
    }

    public function execute(ErrorResponse $response): Response
    {
        return new Response($response->code, $this->headers, $response->body);
    }
}
