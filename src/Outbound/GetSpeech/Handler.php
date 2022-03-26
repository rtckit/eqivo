<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\GetSpeech;

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

use React\EventLoop\Loop;
use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\ESL;
use stdClass as Event;
use function React\Promise\{
    all,
    resolve,
    reject
};

class Handler implements HandlerInterface
{
    use HandlerTrait;

    public const ELEMENT_TYPE = 'GetSpeech';

    public const NESTABLES = ['Speak', 'Play', 'Wait'];

    public const DEFAULT_TIMEOUT = 5;

    public function execute(Session $session, RestXmlElement $element): PromiseInterface
    {
        $context = $this->fetchContext($session, $element);
        $deferred = new Deferred();

        $context->session->client->sendMsg(
            (new ESL\Request\SendMsg)
                ->setHeader('call-command', 'execute')
                ->setHeader('execute-app-name', 'detect_speech')
                ->setHeader('execute-app-arg', 'grammarsalloff')
                ->setHeader('event-lock', 'true')
        )
            ->then(function () use ($context) {
                return $context->session->client->sendMsg(
                    (new ESL\Request\SendMsg)
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'detect_speech')
                        ->setHeader('execute-app-arg', "{$context->engine} {$context->grammar} {$context->grammarPath}/{$context->grammar}.gram")
                        ->setHeader('event-lock', 'true')
                );
            })
            ->then(function (ESL\Response\CommandReply $response) use ($context, $deferred) {
                if (!$response->isSuccessful()) {
                    $this->app->outboundServer->logger->error('GetSpeech Failed - ' . ($response->getBody() ?? '<null>'));

                    $deferred->resolve();

                    return reject();
                }

                return $context->session->client->sendMsg(
                    (new ESL\Request\SendMsg)
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'detect_speech')
                        ->setHeader('execute-app-arg', 'resume')
                        ->setHeader('event-lock', 'true')
                );
            })
            ->then(function () use ($context, $element) {
                $soundFiles = $this->app->outboundServer->controller->buildPlaybackArray(
                    $context->session, $element,
                    [Play\Handler::ELEMENT_TYPE, Speak\Handler::ELEMENT_TYPE, Wait\Handler::ELEMENT_TYPE]
                );

                if ($context->playBeep) {
                    $this->app->outboundServer->logger->debug('GetSpeech play Beep enabled');
                }

                if (!isset($soundFiles[0])) {
                    $playStr = $context->playBeep ? 'tone_stream://%(300,200,700)' : 'silence_stream://10';
                } else {
                    $context->setVars[] = 'playback_delimiter=!';
                    $playStr = 'file_string://silence_stream://1!' . implode('!', $soundFiles);

                    if ($context->playBeep) {
                        $playStr .= '!tone_stream://%(300,200,700)';
                    }
                }

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

                $promises['playback'] = $context->session->client->sendMsg(
                    (new ESL\Request\SendMsg)
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'playback')
                        ->setHeader('execute-app-arg', $playStr)
                        ->setHeader('event-lock', 'true')
                );

                return all($promises);
            })
            ->then(function () use ($context): PromiseInterface {
                return $this->app->outboundServer->controller->waitForEvent($context->session);
            })
            ->then(function (Event $event) use ($context): PromiseInterface {
                $response = $event->{'Application-Response'} ?? '<null>';
                $this->app->outboundServer->logger->debug("GetSpeech prompt played ({$response})");

                $context->timer = Loop::addTimer($context->timeout, function() use ($context): void {
                    unset($context->timer);

                    $this->app->outboundServer->logger->debug('GetSpeech Break (timeout)');
                    $this->app->outboundServer->controller->pushToEventQueue($context->session, null);
                });

                return $context->session->client->sendMsg(
                    (new ESL\Request\SendMsg)
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'detect_speech')
                        ->setHeader('execute-app-arg', 'resume')
                        ->setHeader('event-lock', 'true')
                );
            })
            ->then(function () use ($context): PromiseInterface {
                return $this->waitForEvent($context);
            })
            ->then(function (?string $response = null) use ($context): PromiseInterface {
                return all([
                    'response' => $response,
                    'stop' => $context->session->client->sendMsg(
                        (new ESL\Request\SendMsg)
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'detect_speech')
                            ->setHeader('execute-app-arg', 'stop')
                            ->setHeader('event-lock', 'true')
                    ),
                    'break' => $context->session->client->bgApi(
                        (new ESL\Request\BgApi)->setParameters('uuid_break ' . $context->session->uuid . ' all')
                    ),
                ]);
            })
            ->then(function (array $args) use ($context) {
                if (isset($context->action)) {
                    $params = [
                        'Grammar' => '',
                        'Confidence' => 0,
                        'Mode' => '',
                        'SpeechResult' => '',
                        'SpeechInterpretation' => '',
                    ];

                    if (isset($args['response'])) {
                        $resultXml = simplexml_load_string($args['response']);

                        if ($resultXml === false) {
                            $params['Confidence'] = -1;

                            $this->app->outboundServer->logger->error("GetSpeech result failure, cannot parse result");
                        } else {
                            if ($resultXml->getName() !== 'result') {
                                $params['Confidence'] = -1;

                                $this->app->outboundServer->logger->error("GetSpeech result failure, cannot parse result: No result Tag Present");
                            } else if (!isset($resultXml->interpretation[0])) {
                                $this->app->outboundServer->logger->error("GetSpeech result failure, cannot parse result: No interpretation found");
                            } else {
                                $interpretation = $resultXml->interpretation[0];
                                $attributes = $interpretation->attributes();

                                if (!isset($attributes, $attributes->grammar, $attributes->confidence, $interpretation->input)) {
                                    $this->app->outboundServer->logger->error("GetSpeech result failure, cannot parse result: No valid interpretation found");
                                } else {
                                    $params['Grammar'] = (string)$attributes->grammar;
                                    $params['Confidence'] = (int)$attributes->confidence;
                                    $inputAttrs = $interpretation->input->attributes();
                                    $params['Mode'] = isset($inputAttrs, $inputAttrs->mode) ? (string)$inputAttrs->mode : '';
                                    $params['SpeechResult'] = (string)$interpretation->input;
                                }
                            }
                        }
                    }

                    return $this->app->outboundServer->controller->fetchAndExecuteRestXml(
                        $context->session, $context->action, $context->method, $params
                    )
                        ->then(function () use ($context) {
                            $context->session->currentElement = self::ELEMENT_TYPE;

                            return true;
                        });
                }

                return resolve(false);
            })
            ->then(function (bool $break) use ($deferred) {
                $deferred->resolve($break);
            });

        return $deferred->promise();
    }

    public function fetchContext(Session $session, RestXmlElement $element): Context
    {
        $context = new Context;
        $context->session = $session;

        $attributes = $element->attributes();

        if (!isset($attributes->grammar)) {
            throw new RestXmlAttributeException("GetSpeech 'grammar' is mandatory");
        }

        $context->grammar = (string)$attributes->grammar;

        if (!isset($attributes->engine)) {
            throw new RestXmlAttributeException("GetSpeech 'engine' is mandatory");
        }

        $context->grammarPath = isset($attributes->grammarPath) ? (string)$attributes->grammarPath : '/usr/local/freeswitch/grammar/';

        $context->engine = (string)$attributes->engine;

        if (isset($attributes->timeout)) {
            $context->timeout = (int)$attributes->timeout;

            if ($context->timeout > self::DEFAULT_TIMEOUT) {
                $context->timeout = self::DEFAULT_TIMEOUT;
            } else if ($context->timeout < 1) {
                throw new RestXmlAttributeException("GetSpeech 'timeout' must be a positive integer");
            } else {
                $context->timeout = self::DEFAULT_TIMEOUT;
            }
        } else {
            $context->timeout = self::DEFAULT_TIMEOUT;
        }

        $context->playBeep = isset($attributes->playBeep) ? ((string)$attributes->playBeep === 'true') : false;

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

    protected function waitForEvent(Context $context): PromiseInterface
    {
        return $this->app->outboundServer->controller->waitForEvent($context->session)
            ->then(function (?Event $event) use ($context): PromiseInterface {
                if (!isset($event)) {
                    $this->app->outboundServer->logger->warning('GetSpeech Break (empty event)');

                    return resolve();
                } else if (($event->{'Event-Name'} === 'DETECTED_SPEECH') && ($event->{'Speech-Type'} === 'detected-speech')) {
                    Loop::cancelTimer($context->timer);

                    $this->app->outboundServer->logger->info("GetSpeech, result '{$event->_body}'");

                    return resolve($event->_body);
                }

                return $this->waitForEvent($context);
            });
    }
}
