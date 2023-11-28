<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\PreAnswer;

use function React\Promise\resolve;
use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\FiCore\Exception\{
    RestXmlAttributeException,
    RestXmlFormatException
};

use RTCKit\FiCore\Plan\{
    AbstractElement,
    HandlerInterface,
    HandlerTrait
};

use RTCKit\FiCore\Switch\{
    Channel,
    RedirectCauseEnum
};

class Handler implements HandlerInterface
{
    use HandlerTrait;

    public function execute(Channel $channel, AbstractElement $element): PromiseInterface
    {
        assert($element instanceof Element);

        $element->origElements = $channel->elements;
        $channel->elements = $element->elements;
        $element->origElement = $channel->currentElement;
        $channel->preAnswer = true;

        return $this->app->planConsumer->rewindExecuteSequence($channel)
            ->then(function () use ($element): PromiseInterface {
                $element->channel->elements = $element->origElements;
                $element->channel->currentElement = $element->origElement;
                $element->channel->preAnswer = false;

                $this->app->planConsumer->logger->info('PreAnswer Completed');

                return resolve(null);
            });
    }
}
