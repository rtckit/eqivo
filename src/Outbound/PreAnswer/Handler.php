<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\PreAnswer;

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

    public const ELEMENT_TYPE = 'PreAnswer';

    public const NO_ANSWER = true;

    public const NESTABLES = [
        'GetDigits',
        'GetSpeech',
        'Play',
        'Redirect',
        'Speak',
        'SIPTransfer',
        'Wait',
    ];

    public function execute(Session $session, RestXmlElement $element): PromiseInterface
    {
        $context = $this->fetchContext($session, $element);
        $session->restXml = $element;
        $session->preAnswer = true;

        return $this->app->outboundServer->controller->executeRestXml($session)
            ->then(function () use ($context): PromiseInterface {
                $context->session->restXml = $context->origRestXml;
                $context->session->currentElement = $context->origElement;
                $context->session->preAnswer = false;

                $this->app->outboundServer->logger->info('PreAnswer Completed');

                return resolve();
            });
    }

    public function fetchContext(Session $session, RestXmlElement $element): Context
    {
        $context = new Context;
        $context->session = $session;
        $context->origRestXml = $session->restXml;
        $context->origElement = $session->currentElement;

        return $context;
    }
}
