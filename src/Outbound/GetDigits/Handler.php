<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\GetDigits;

use RTCKit\Eqivo\{
    HangupCauseEnum,
    Session
};
use RTCKit\Eqivo\Exception\{
    RestXmlAttributeException,
    RestXmlFormatException
};
use RTCKit\Eqivo\Outbound\{
    HandlerInterface,
    HandlerTrait,
    Play,
    RestXmlElement,
    Speak,
    Wait
};

use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\ESL;
use function React\Promise\{
    all,
    resolve
};

class Handler implements HandlerInterface
{
    use HandlerTrait;

    public const ELEMENT_TYPE = 'GetDigits';

    public const NESTABLES = ['Speak', 'Play', 'Wait'];

    public const DEFAULT_MAX_DIGITS = 99;

    public const DEFAULT_TIMEOUT = 5;

    public function execute(Session $session, RestXmlElement $element): PromiseInterface
    {
        $context = $this->fetchContext($session, $element);
        $soundFiles = $this->app->outboundServer->controller->buildPlaybackArray(
            $session, $element,
            [Play\Handler::ELEMENT_TYPE, Speak\Handler::ELEMENT_TYPE, Wait\Handler::ELEMENT_TYPE]
        );

        $this->app->outboundServer->logger->info('GetDigits Started ' . json_encode($soundFiles));

        if ($context->playBeep) {
            $this->app->outboundServer->logger->info('GetDigits play Beep enabled');
        }

        $playStr = '';

        if (!isset($soundFiles[0])) {
            $playStr = $context->playBeep ? 'tone_stream://%(300,200,700)' : 'silence_stream://10';
        } else {
            $context->setVars[] = 'playback_delimiter=!';
            $playStr = 'file_string://silence_stream://1!' . implode('!', $soundFiles);

            if ($context->playBeep) {
                $playStr .= '!tone_stream://%(300,200,700)';
            }
        }

        if (!isset($context->invalidDigitsSound[0])) {
            $context->invalidDigitsSound = 'silence_stream://150';
        }

        $digitTimeout = $context->timeout;
        $digits = str_split($context->validDigits);

        foreach ($digits as $idx => $digit) {
            if ($digit === '*') {
                $digits[$idx] = '\*';
            }
        }

        $regExp = '^(' . implode('|', $digits) . ')+';
        $playStr = str_replace("'", "\\'", $playStr);

        $promises = [];

        if (isset($context->setVars[0])) {
            $promises['set'] = $context->session->client->sendMsg(
                (new ESL\Request\SendMsg)
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'multiset')
                    ->setHeader('execute-app-arg', implode(' ', $context->setVars))
                    ->setHeader('event-lock', 'true')
            );
        }

        $promises['pagd'] = $context->session->client->sendMsg(
            (new ESL\Request\SendMsg)
                ->setHeader('call-command', 'execute')
                ->setHeader('execute-app-name', 'play_and_get_digits')
                ->setHeader(
                    'execute-app-arg',
                    '1 ' . $context->numDigits . ' ' .
                    $context->tries . ' ' . $context->timeout . " '" .
                    $context->finishOnKey . "' '" . $playStr . "' " .
                    $context->invalidDigitsSound . ' pagd_input ' .
                    $regExp . ' ' . $digitTimeout
                )
                ->setHeader('event-lock', 'true')
        );

        return all($promises)
            ->then(function () use ($context): PromiseInterface {
                return $this->app->outboundServer->controller->waitForEvent($context->session);
            })
            ->then(function () use ($context): PromiseInterface {
                return $this->app->outboundServer->controller->getVariable($context->session, 'pagd_input');
            })
            ->then(function (?string $digits) use ($context): PromiseInterface {
                if (isset($digits, $digits[0])) {
                    $this->app->outboundServer->logger->info("GetDigits, Digits '{$digits}' Received");

                    if (isset($context->action)) {
                        return $this->app->outboundServer->controller->fetchAndExecuteRestXml(
                            $context->session, $context->action, $context->method, ['Digits' => $digits]
                        )
                            ->then(function () {
                                return resolve(true);
                            });
                    }
                } else {
                    $this->app->outboundServer->logger->info('GetDigits, No Digits Received');
                }

                return resolve();
            });
    }

    public function fetchContext(Session $session, RestXmlElement $element): Context
    {
        $context = new Context;
        $context->session = $session;

        $attributes = $element->attributes();

        if (isset($attributes->numDigits)) {
            $context->numDigits = (int)$attributes->numDigits;

            if ($context->numDigits > self::DEFAULT_MAX_DIGITS) {
                $context->numDigits = self::DEFAULT_MAX_DIGITS;
            } else if ($context->numDigits < 1) {
                throw new RestXmlAttributeException("GetDigits 'numDigits' must be greater than 0");
            } else {
                $context->numDigits = self::DEFAULT_MAX_DIGITS;
            }
        } else {
            $context->numDigits = self::DEFAULT_MAX_DIGITS;
        }

        if (isset($attributes->timeout)) {
            $context->timeout = (int)$attributes->timeout;

            if ($context->timeout > self::DEFAULT_TIMEOUT) {
                $context->timeout = self::DEFAULT_TIMEOUT;
            } else if ($context->timeout < 1) {
                throw new RestXmlAttributeException("GetDigits 'timeout' must be a positive integer");
            } else {
                $context->timeout = self::DEFAULT_TIMEOUT;
            }
        } else {
            $context->timeout = self::DEFAULT_TIMEOUT;
        }

        $context->timeout *= 1000;

        $context->finishOnKey = isset($attributes->finishOnKey) ? (string)$attributes->finishOnKey : '#';
        $context->playBeep = isset($attributes->playBeep) ? ((string)$attributes->playBeep === 'true') : false;
        $context->invalidDigitsSound = isset($attributes->invalidDigitsSound) ? (string)$attributes->invalidDigitsSound : '';
        $context->validDigits = isset($attributes->validDigits) ? (string)$attributes->validDigits : '0123456789*#';

        if (isset($attributes->tries)) {
            $context->tries = (int)$attributes->tries;

            if ($context->tries < 1) {
                throw new RestXmlAttributeException("GetDigits 'tries' must be greater than 0");
            }
        } else {
            $context->tries = 1;
        }

        if (isset($attributes->method)) {
            if (!in_array((string)$attributes->method, ['GET', 'POST'])) {
                throw new RestXmlAttributeException("method must be 'GET' or 'POST'");
            }

            $context->method = (string)$attributes->method;
        } else {
            $context->method = 'POST';
        }

        if (isset($attributes->action) && filter_var((string)$attributes->action, FILTER_VALIDATE_URL)) {
            $context->action = (string)$attributes->action;
        }

        return $context;
    }
}
