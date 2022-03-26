<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\Hangup;

use RTCKit\Eqivo\{
    HangupCauseEnum,
    Session
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
use RTCKit\ESL;
use function React\Promise\resolve;

class Handler implements HandlerInterface
{
    use HandlerTrait;

    public const ELEMENT_TYPE = 'Hangup';

    public const NO_ANSWER = true;

    public function execute(Session $session, RestXmlElement $element): PromiseInterface
    {
        $context = $this->fetchContext($session, $element);

        if ($context->schedule > 0) {
            return $context->session->client->sendMsg(
                (new ESL\Request\SendMsg)
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'sched_hangup')
                    ->setHeader('execute-app-arg', "+{$context->schedule} {$context->reason}")
                    ->setHeader('event-lock', 'true')
            )
                ->then(function (ESL\Response\CommandReply $response) use ($context): PromiseInterface {
                    if ($response->isSuccessful()) {
                        $this->app->outboundServer->logger->info("Hangup (scheduled) will be fired in {$context->schedule} secs!");
                    } else {
                        $this->app->outboundServer->logger->error('Hangup (scheduled) Failed: ' . ($response->getBody() ?? '<null>'));
                    }

                    return resolve();
                });
        }

        /* Not part of the legacy implementation, but it makes sense (for now) */
        $context->session->hangupCause = HangupCauseEnum::from($context->reason);

        $this->app->outboundServer->logger->info("Hanging up now ({$context->reason})");

        return $context->session->client->sendMsg(
            (new ESL\Request\SendMsg)
                ->setHeader('call-command', 'execute')
                ->setHeader('execute-app-name', 'hangup')
                ->setHeader('execute-app-arg', $context->reason)
                ->setHeader('event-lock', 'true')
        )
            ->then(function (): PromiseInterface {
                return resolve(true);
            });
    }

    public function fetchContext(Session $session, RestXmlElement $element): Context
    {
        $context = new Context;
        $context->session = $session;

        $attributes = $element->attributes();

        if (isset($attributes->schedule)) {
            $schedule = (int)$attributes->schedule;
            $context->schedule = ($schedule < 1) ? 0 : $schedule;
        } else {
            $context->schedule = 0;
        }

        if (isset($attributes->reason)) {
            switch ($attributes->reason) {
                case 'rejected':
                    $context->reason = HangupCauseEnum::CALL_REJECTED->value;
                    break;

                case 'busy':
                    $context->reason = HangupCauseEnum::USER_BUSY->value;
                    break;

                default:
                    $context->reason = HangupCauseEnum::NORMAL_CLEARING->value;
                    break;
            }
        } else {
            $context->reason = HangupCauseEnum::NORMAL_CLEARING->value;
        }

        return $context;
    }
}
