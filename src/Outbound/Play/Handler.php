<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\Play;

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
use stdClass as Event;
use function React\Promise\resolve;

class Handler implements HandlerInterface
{
    use HandlerTrait;

    public const ELEMENT_TYPE = 'Play';

    public const MAX_LOOPS = 10000;

    public function execute(Session $session, RestXmlElement $element): PromiseInterface
    {
        $context = $this->fetchContext($session, $element);

        if (empty($context->url)) {
            $this->app->outboundServer->logger->error('Invalid Sound File - Ignoring Play');

            return resolve();
        }

        $playStr = '';
        $context->setVars[] = 'playback_sleep_val=0';

        if ($context->loop === 1) {
            $playStr = $context->url;
        } else {
            $context->setVars[] = 'playback_delimiter=!';
            $playStr = 'file_string://silence_stream://1' . str_repeat('!' . $context->url, $context->loop);
        }

        $this->app->outboundServer->logger->debug("Playing {$context->loop} times");

        return $context->session->client->sendMsg(
            (new ESL\Request\SendMsg)
                ->setHeader('call-command', 'execute')
                ->setHeader('execute-app-name', 'multiset')
                ->setHeader('execute-app-arg', implode(' ', $context->setVars))
                ->setHeader('event-lock', 'true')
        )
            ->then(function () use ($context, $playStr) {
                return $context->session->client->sendMsg(
                    (new ESL\Request\SendMsg)
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'playback')
                        ->setHeader('execute-app-arg', $playStr)
                        ->setHeader('event-lock', 'true')
                );
            })
            ->then(function (ESL\Response $response) use ($context) {
                if (!($response instanceof ESL\Response\CommandReply) || !$response->isSuccessful()) {
                    $this->app->outboundServer->logger->error('Play Failed - ' . ($response->getBody() ?? '<null>'));

                    return resolve();
                }

                return $this->app->outboundServer->controller->waitForEvent($context->session)
                    ->then (function (?Event $event = null) {
                        if (!isset($event)) {
                            $this->app->outboundServer->logger->warning('Play Break (empty event)');
                        } else {
                            $this->app->outboundServer->logger->debug("Play done ({$event->{'Application-Response'}})");
                        }

                        $this->app->outboundServer->logger->info('Play Finished');

                        return resolve();
                    });
            });
    }

    public function fetchContext(Session $session, RestXmlElement $element): Context
    {
        $context = new Context;
        $context->session = $session;

        $attributes = $element->attributes();

        if (isset($attributes->loop)) {
            $loop = (int)$attributes->loop;

            if ($loop < 0) {
                throw new RestXmlFormatException("Play 'loop' must be a positive integer or 0");
            }

            if (!$loop || ($loop > self::MAX_LOOPS)) {
                $loop = self::MAX_LOOPS;
            }

            $context->loop = $loop;
        } else {
            $context->loop = 1;
        }

        $context->url = trim((string)$element);

        if (!strlen($context->url)) {
            throw new RestXmlFormatException("No File to play set!");
        }

        return $context;
    }
}
