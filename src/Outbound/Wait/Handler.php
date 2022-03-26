<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\Wait;

use RTCKit\Eqivo\Session;
use RTCKit\Eqivo\Exception\RestXmlFormatException;
use RTCKit\Eqivo\Outbound\{
    HandlerInterface,
    HandlerTrait,
    RestXmlElement
};

use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\ESL;
use function React\Promise\resolve;

class Handler implements HandlerInterface
{
    use HandlerTrait;

    public const ELEMENT_TYPE = 'Wait';

    public const NO_ANSWER = true;

    public function execute(Session $session, RestXmlElement $element): PromiseInterface
    {
        try {
            $context = $this->fetchContext($session, $element);
        } catch (RestXmlFormatException $e) {
            $this->app->outboundServer->logger->error('RestXML format exception: ' . $e->getMessage());
            return resolve();
        }

        $this->app->outboundServer->logger->info("Wait Started for {$context->length} seconds");

        $pauseStr = 'file_string://silence_stream://' . ($context->length * 1000);

        return $context->session->client->sendMsg(
            (new ESL\Request\SendMsg)
                ->setHeader('call-command', 'execute')
                ->setHeader('execute-app-name', 'playback')
                ->setHeader('execute-app-arg', $pauseStr)
                ->setHeader('event-lock', 'true')
        )
            ->then(function () use ($context) {
                return $this->app->outboundServer->controller->waitForEvent($context->session)
                    ->then(function () {
                        return resolve();
                    });
            });
        }

    public function fetchContext(Session $session, RestXmlElement $element): Context
    {
        $context = new Context;
        $context->session = $session;

        $attributes = $element->attributes();

        if (isset($attributes->length)) {
            $length = (int)$attributes->length;

            if ($length < 1) {
                throw new RestXmlFormatException("Wait 'length' must be a positive integer");
            }

            $context->length = $length;
        } else {
            $context->length = 1;
        }

        return $context;
    }
}
