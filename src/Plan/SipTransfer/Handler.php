<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\SipTransfer;

use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\ESL;

use RTCKit\FiCore\Plan\{
    AbstractElement,
    HandlerInterface,
    HandlerTrait,
};
use RTCKit\FiCore\Switch\{
    Channel,
    RedirectCauseEnum,
};

class Handler implements HandlerInterface
{
    use HandlerTrait;

    public function execute(Channel $channel, AbstractElement $element): PromiseInterface
    {
        assert($element instanceof Element);

        $sipTransferUri = $channel->answered ? $element->uris[0] : implode(',', $element->uris);

        if ($channel->answered) {
            $this->app->planConsumer->logger->debug('SIPTransfer using deflect');

            $promise = $element->channel->client->sendMsg(
                (new ESL\Request\SendMsg())
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'deflect')
                    ->setHeader('execute-app-arg', $sipTransferUri)
                    ->setHeader('event-lock', 'true')
            );
        } else {
            $this->app->planConsumer->logger->debug('SIPTransfer using redirect');

            $promise = $element->channel->client->sendMsg(
                (new ESL\Request\SendMsg())
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'redirect')
                    ->setHeader('execute-app-arg', $sipTransferUri)
                    ->setHeader('event-lock', 'true')
            );
        }

        return $promise
            ->then(function (): bool {
                return true;
            });
    }
}
