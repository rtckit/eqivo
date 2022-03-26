<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\Redirect;

use RTCKit\Eqivo\{
    RedirectCauseEnum,
    Session
};
use RTCKit\Eqivo\Exception\{
    RestXmlAttributeException,
    RestXmlFormatException
};
use RTCKit\Eqivo\Outbound\{
    HandlerInterface,
    HandlerTrait,
    RestXmlElement
};

use React\Promise\{
    Deferred,
    PromiseInterface
};
use function React\Promise\resolve;

class Handler implements HandlerInterface
{
    use HandlerTrait;

    public const ELEMENT_TYPE = 'Redirect';

    public const NO_ANSWER = true;

    public function execute(Session $session, RestXmlElement $element): PromiseInterface
    {
        $context = $this->fetchContext($session, $element);

        return $this->app->outboundServer->controller->fetchAndExecuteRestXml($session, $context->url, $context->method)
            ->then(function (): PromiseInterface {
                return resolve(true);
            });
    }

    public function fetchContext(Session $session, RestXmlElement $element): Context
    {
        $context = new Context;
        $context->session = $session;

        $attributes = $element->attributes();

        if (isset($attributes->method)) {
            if (!in_array((string)$attributes->method, ['GET', 'POST'])) {
                throw new RestXmlAttributeException("Method must be 'GET' or 'POST'");
            }

            $context->method = (string)$attributes->method;
        } else {
            $context->method = 'POST';
        }

        $context->url = trim((string)$element);

        if (!strlen($context->url)) {
            throw new RestXmlFormatException("Redirect must have an URL");
        }

        if (!(filter_var($context->url, FILTER_VALIDATE_URL))) {
            throw new RestXmlFormatException("Redirect URL '{$context->url}' not valid!");
        }

        return $context;
    }
}
