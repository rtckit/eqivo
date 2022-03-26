<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\Speak;

use RTCKit\Eqivo\{
    HangupCauseEnum,
    Session
};
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

    public const ELEMENT_TYPE = 'Speak';

    public const MAX_LOOPS = 10000;

    public const METHODS = [
        'N/A',
        'PRONOUNCED',
        'ITERATED',
        'COUNTED',
        'PRONOUNCED_YEAR'
    ];

    public const TYPES = [
        'NUMBER',
        'ITEMS',
        'PERSONS',
        'MESSAGES',
        'CURRENCY',
        'TIME_MEASUREMENT',
        'CURRENT_DATE',
        'CURRENT_TIME',
        'CURRENT_DATE_TIME',
        'TELEPHONE_NUMBER',
        'TELEPHONE_EXTENSION',
        'URL',
        'IP_ADDRESS',
        'EMAIL_ADDRESS',
        'POSTAL_ADDRESS',
        'ACCOUNT_NUMBER',
        'NAME_SPELLED',
        'NAME_PHONETIC',
        'SHORT_DATE_TIME',
    ];

    public function execute(Session $session, RestXmlElement $element): PromiseInterface
    {
        $context = $this->fetchContext($session, $element);

        $sayArgs = '';

        if (isset($context->type) && isset($context->method)) {
            $promise = $context->session->client->sendMsg(
                (new ESL\Request\SendMsg)
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'say')
                    ->setHeader('execute-app-arg', "{$context->language} {$context->type} {$context->method} {$context->text}")
                    ->setHeader('event-lock', 'true')
                    ->setHeader('loops', (string)$context->loop)
            );
        } else {
            $promise = $context->session->client->sendMsg(
                (new ESL\Request\SendMsg)
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'speak')
                    ->setHeader('execute-app-arg', "{$context->engine}|{$context->voice}|{$context->text}")
                    ->setHeader('event-lock', 'true')
                    ->setHeader('loops', (string)$context->loop)
            );
        }

        return $promise
            ->then(function (ESL\Response\CommandReply $response) use ($context): PromiseInterface {
                if (!$response->isSuccessful()) {
                    $this->app->outboundServer->logger->error('Speak Failed - ' . ($response->getBody() ?? '<null>'));

                    return resolve();
                }

                $context->current = 0;

                return $this->waitForEvent($context);
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
                throw new RestXmlFormatException("Speak 'loop' must be a positive integer or 0");
            }

            if (!$loop || ($loop > self::MAX_LOOPS)) {
                $loop = self::MAX_LOOPS;
            }

            $context->loop = $loop;
        } else {
            $context->loop = 1;
        }

        $context->engine = isset($attributes->engine) ? (string)$attributes->engine : 'flite';
        $context->language = isset($attributes->language) ? (string)$attributes->language : 'en';
        $context->voice = isset($attributes->voice) ? (string)$attributes->voice : 'slt';

        if (isset($attributes->type) && in_array((string)$attributes->type, self::TYPES)) {
            $context->type = (string)$attributes->type;
        }

        if (isset($attributes->method) && in_array((string)$attributes->method, self::METHODS)) {
            $context->method = (string)$attributes->method;
        }

        $context->text = trim((string)$element);

        return $context;
    }

    protected function waitForEvent(Context $context): PromiseInterface
    {
        if ($context->current >= $context->loop) {
            $this->app->outboundServer->logger->info('Speak Finished');

            return resolve();
        }

        $this->app->outboundServer->logger->debug('Speaking ' . ($context->current + 1) . ' times ...');

        return $this->app->outboundServer->controller->waitForEvent($context->session)
            ->then(function (?Event $event) use ($context): PromiseInterface {
                if (!isset($event)) {
                    $this->app->outboundServer->logger->warning('Speak Break (empty event)');

                    return resolve();
                }

                $this->app->outboundServer->logger->debug('Speak ' . ++$context->current . ' times done (' . $event->{'Application-Response'} . ')');

                return $this->waitForEvent($context);
            });
    }
}
