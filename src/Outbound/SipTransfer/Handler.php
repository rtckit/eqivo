<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\SipTransfer;

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
use RTCKit\ESL;
use RTCKit\SIP;
use function React\Promise\resolve;

class Handler implements HandlerInterface
{
    use HandlerTrait;

    public const ELEMENT_TYPE = 'SIPTransfer';

    public const NO_ANSWER = true;

    public const ALLOWED_SCHEMES = ['sip', 'sips'];

    public function execute(Session $session, RestXmlElement $element): PromiseInterface
    {
        $context = $this->fetchContext($session, $element);

        if (!isset($context->urls[0])) {
            throw new RestXmlFormatException('SIPTransfer must have a sip uri');
        }

        $session->sipTransfer = true;
        $session->sipTransferUri = implode(',', $context->urls);

        if ($session->answered) {
            $this->app->outboundServer->logger->debug('SIPTransfer using deflect');

            $promise = $context->session->client->sendMsg(
                (new ESL\Request\SendMsg)
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'deflect')
                    ->setHeader('execute-app-arg', $session->sipTransferUri)
                    ->setHeader('event-lock', 'true')
            );
        } else {
            $this->app->outboundServer->logger->debug('SIPTransfer using redirect');

            $promise = $context->session->client->sendMsg(
                (new ESL\Request\SendMsg)
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'redirect')
                    ->setHeader('execute-app-arg', $session->sipTransferUri)
                    ->setHeader('event-lock', 'true')
            );
        }

        return $promise
            ->then(function (): PromiseInterface {
                return resolve(true);
            });
    }

    public function fetchContext(Session $session, RestXmlElement $element): Context
    {
        $context = new Context;
        $context->session = $session;

        $urls = explode(',', trim((string)$element));

        foreach ($urls as $url) {
            try {
                $parsed = SIP\URI::parse($url);

                if (isset($parsed->scheme) && in_array($parsed->scheme, self::ALLOWED_SCHEMES)) {
                    $context->urls[] = $parsed->render();
                }
            } catch (SIP\Exception\InvalidURIException $e) {
                $this->app->outboundServer->logger->error("Invalid SIP URI: {$url}");
            }
        }

        return $context;
    }
}
