<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound;

use RTCKit\Eqivo\{
    App,
    Config,
    EventEnum,
    HangupCauseEnum,
    Session,
    StatusEnum
};
use RTCKit\Eqivo\Exception\{
    HangupException,
    MethodNotAllowedException,
    RestXmlFormatException,
    RestXmlSyntaxException,
    UnrecognizedRestXmlException
};

use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\ESL;
use RTCKit\React\ESL\RemoteOutboundClient;
use RTCKit\SIP\Header\NameAddrHeader;
use RTCKit\SIP\Exception\SIPException;
use SimpleXMLIterator;
use stdClass as Event;
use function React\Promise\{
    reject,
    resolve
};

class Controller implements ControllerInterface
{
    public const WAIT_FOR_APPLICATIONS = [
        'playback',
        'record',
        'play_and_get_digits',
        'bridge',
        'say',
        'sleep',
        'speak',
        'conference',
        'park',
    ];

    protected App $app;

    public function setApp(App $app): static
    {
        $this->app = $app;

        return $this;
    }

    public function onConnect(RemoteOutboundClient $client, ESL\Response\CommandReply $response): void
    {
        $logger = $this->app->outboundServer->logger;
        $context = $response->getHeaders();

        $core = $this->app->getCore($context['core-uuid']);

        if (!isset($core)) {
            $logger->warning("Cannot accept connection from unknown core '{$context['core-uuid']}'");
            $client->close();

            return;
        }

        $outbound = $context['call-direction'] === 'outbound';
        $targetUrl = null;

        if (!empty($context["variable_{$this->app->config->appPrefix}_transfer_url"])) {
            $targetUrl = urldecode($context["variable_{$this->app->config->appPrefix}_transfer_url"]);

            $logger->info('Using TransferUrl ' . $targetUrl);
        } else if (!empty($context["variable_{$this->app->config->appPrefix}_answer_url"])) {
            $targetUrl = urldecode($context["variable_{$this->app->config->appPrefix}_answer_url"]);

            $logger->info('Using AnswerUrl ' . $targetUrl);
        } else if (!$outbound && !empty($this->app->config->defaultAnswerUrl))  {
            $targetUrl = $this->app->config->defaultAnswerUrl;

            $logger->info('Using DefaultAnswerUrl ' . $targetUrl);
        } else {
            $logger->error('Aborting -- No Call Url found!');
            $client->close();

            return;
        }

        $session = $core->getSession($context['channel-unique-id']);

        if (!isset($session)) {
            $session = new Session;
            $session->context = $context;
            $session->uuid = $session->context['channel-unique-id'];

            $core->addSession($session);
        } else {
            $session->context = $context;
        }

        $session->client = $client;
        $session->coreUuid = $session->context['core-uuid'];
        $session->callerName = urldecode($session->context['caller-caller-id-name'] ?? '');
        $session->outbound = $outbound;
        $session->targetUrl = $targetUrl;

        $session->client->resume();
        $session->client->linger();
        $session->client->myEvents('json');
        $session->client->divertEvents('on');
        $session->client->event("json CUSTOM conference::maintenance {$this->app->config->appPrefix}::dial");
        $session->client->sendMsg(
            (new ESL\Request\SendMsg)
                ->setHeader('call-command', 'execute')
                ->setHeader('execute-app-name', 'multiset')
                ->setHeader('execute-app-arg', $this->app->config->appPrefix . '_app=true hangup_after_bridge=false')
                ->setHeader('event-lock', 'true')
        );

        $session->client->on('event', function(ESL\Response\TextEventJson $response) use ($logger, $session): void {
            $event = json_decode($response->getBody() ?? '');
            assert($event instanceof Event);

            try {
                $this->onEvent($session, $event);
            } catch (\Throwable $t) {
                $t = $t->getPrevious() ?: $t;

                $logger->error('Processing outbound event failure: ' . $t->getMessage(), [
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ]);
            }
        });

        if ($session->outbound) {
            $session->calledNumber = urldecode(
                $session->context["variable_{$this->app->config->appPrefix}_to"]
                ?? $session->context['caller-destination-number']
                ?? ''
            );
            $session->callerNumber = urldecode(
                $session->context["variable_{$this->app->config->appPrefix}_from"]
                ?? $session->context['caller-caller-id-number']
                ?? ''
            );

            $session->aLegUuid = $session->context['caller-unique-id'];
            $session->aLegRequestUuid = $session->context["variable_{$this->app->config->appPrefix}_request_uuid"];

            if (!empty($session->context["variable_{$this->app->config->appPrefix}_sched_hangup_id"])) {
                $session->schedHangupId = $session->context["variable_{$this->app->config->appPrefix}_sched_hangup_id"];
            }

            $session->status = StatusEnum::InProgress;
            $session->answered = true;

            if (!empty($session->context["variable_{$this->app->config->appPrefix}_accountsid"])) {
                $session->accountSid = $session->context["variable_{$this->app->config->appPrefix}_accountsid"];
            }
        } else {
            $session->calledNumber = urldecode(
                $session->context["variable_{$this->app->config->appPrefix}_destination_number"]
                ?? $session->context['caller-destination-number']
                ?? ''
            );
            $session->callerNumber = urldecode($session->context['caller-caller-id-number'] ?? '');

            if (isset($session->context['variable_sip_h_Diversion'])) {
                try {
                    $diversion = NameAddrHeader::parse([$session->context['variable_sip_h_Diversion']]);

                    if (isset($diversion->uri, $diversion->uri->user)) {
                        $session->forwardedFrom = ltrim($diversion->uri->user, '+');
                    }
                } catch (SIPException $e) {
                    $logger->error("Cannot parse Diversion SIP header '{$session->context['variable_sip_h_Diversion']}'");
                }
            }

            if (!empty($session->context["{$this->app->config->appPrefix}_sched_hangup_id"])) {
                $session->schedHangupId = $session->context["variable_{$this->app->config->appPrefix}_sched_hangup_id"];
            }

            $session->status = StatusEnum::Ringing;
        }

        $session->to = ltrim($session->calledNumber, '+');
        $session->from = ltrim($session->callerNumber, '+');

        if (isset($session->schedHangupId)) {
            $session->client->sendMsg(
                (new ESL\Request\SendMsg)
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'unset')
                    ->setHeader('execute-app-arg', "{$this->app->config->appPrefix}_sched_hangup_id")
                    ->setHeader('event-lock', 'true')
            );
        }

        $logger->info('Processing Call');

        $this->fetchAndExecuteRestXml($session, $session->targetUrl)
            ->then(function () use ($session): PromiseInterface {
                if (isset($session->hangupCause)) {
                    throw new HangupException;
                }

                return $this->getVariable($session, "{$this->app->config->appPrefix}_transfer_progress");
            })
            ->then(function (?string $transferProgress) use ($session, $logger): void {
                if (!isset($session->hangupCause)) {
                    if ($transferProgress) {
                        $logger->info('Transfer In Progress!');
                    } else {
                        $logger->info('No more Elements, Hangup Now!');
                        $session->status = StatusEnum::Completed;
                        $session->hangupCause = HangupCauseEnum::NORMAL_CLEARING;

                        $this->hangup($session);
                    }

                    $logger->info('End of RESTXML');
                }
            })
            ->otherwise(function (HangupException $t) use ($logger) {
                $logger->warning('Channel has hung up, breaking Processing Call');
            })
            ->otherwise(function (\Throwable $t) use ($logger) {
                $t = $t->getPrevious() ?: $t;

                $logger->error('Processing call failure: ' . $t->getMessage(), [
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ]);
            })
            ->always(function() use ($session, $logger) {
                $this->disconnect($session);
                $logger->info('Processing Call Ended');
            });
    }

    /**
     * Fetches then executes remote RestXML
     *
     * @param Session $session
     * @param string $url
     * @param string $method
     * @param array<string, mixed> $params
     *
     * @throws HangupException
     *
     * @return PromiseInterface
     */
    public function fetchAndExecuteRestXml(Session $session, string $url, string $method = 'POST', array $params = []): PromiseInterface
    {
        $logger = $this->app->outboundServer->logger;

        return $this->fetchRestXml($session, $url, $method, $params)
            ->then(function (?RestXmlElement $restXml = null) use ($session): PromiseInterface {
                if (!isset($restXml)) {
                    return resolve();
                }

                if (isset($session->hangupCause)) {
                    throw new HangupException;
                }

                $session->restXml = $restXml;

                return $this->executeRestXml($session);
            })
            ->otherwise(function (HangupException $t) use ($logger) {
                $logger->warning('Channel has hung up, breaking Processing Call');
            })
            ->otherwise(function (\Throwable $t) use ($logger) {
                $t = $t->getPrevious() ?: $t;

                $logger->error('Processing call failure: ' . $t->getMessage(), [
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ]);
            });
    }

    /**
     * Fetches remote RestXML
     *
     * @param Session $session
     * @param string $url
     * @param string $method
     * @param array<string, mixed> $params
     *
     * @throws RestXmlFormatException
     * @throws RestXmlSyntaxException
     *
     * @return PromiseInterface
     */
    public function fetchRestXml(Session $session, string $url, string $method = 'POST', array $params = []): PromiseInterface
    {
        $params = array_merge($session->getPayload(), $params);

        return $this->app->httpClient->makeRequest($url, $method, $params)
            ->then(function (ResponseInterface $response) use ($method, $url, $params): PromiseInterface {
                $this->app->outboundServer->logger->info("Fetched {$method} {$url} with " . json_encode($params));

                $xmlStr = (string)$response->getBody();

                if (!strlen($xmlStr)) {
                    $this->app->outboundServer->logger->warning('No XML Response');

                    return resolve();
                }

                $restXml = simplexml_load_string($xmlStr, RestXmlElement::class);

                if ($restXml === false) {
                    throw new RestXmlSyntaxException('Invalid RESTXML Response Syntax: ' . $xmlStr);
                }

                if ($restXml->getName() !== 'Response') {
                    throw new RestXmlFormatException('No Response Tag Present');
                }

                return resolve($restXml);
            })
            ->otherwise(function (\Throwable $t) {
                $t = $t->getPrevious() ?: $t;

                $this->app->outboundServer->logger->error('Processing call failure: ' . $t->getMessage(), [
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ]);
            });
    }

    public function executeRestXml(Session $session): PromiseInterface
    {
        $session->restXml->rewind();

        return $this->loopXmlResponse($session);
    }

    public function loopXmlResponse(Session $session): PromiseInterface
    {
        if (!$session->restXml->valid()) {
            return resolve();
        }

        $element = $session->restXml->current();
        assert($element instanceof SimpleXMLIterator);

        return $this->executeXmlElement($session, $element)
            ->then(function (?bool $break = null) use ($session): PromiseInterface {
                $this->app->outboundServer->logger->info("[{$session->currentElement}] Done");

                if ($break) {
                    return resolve();
                }

                /* Modeled after:
                 * https://github.com/plivo/plivoframework/blob/29fc41fb3c887d5d9022a941e87bbeb2269112ff/src/plivo/rest/freeswitch/outboundsocket.py#L530-L531
                 */
                if (isset($session->transferInProgress)) {
                    $this->app->outboundServer->logger->info("Transfer in progress, breaking redirect");

                    return resolve();
                }

                $session->restXml->next();

                return $this->loopXmlResponse($session);
            })
            ->otherwise(function (\Throwable $t) {
                $t = $t->getPrevious() ?: $t;

                $this->app->outboundServer->logger->error('Outbound exception: ' . $t->getMessage(), [
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ]);
            });
    }

    public function executeXmlElement(Session $session, SimpleXMLIterator $element): PromiseInterface
    {
        $elementType = $element->getName();
        $session->currentElement = $elementType;

        if (!isset($this->app->outboundServer->handlers[$elementType])) {
            throw new RestXmlFormatException('Unrecognized Element: ' . $elementType);
        }

        $handler = $this->app->outboundServer->handlers[$elementType];

        if (empty($handler::NESTABLES)) {
            if ($element->hasChildren()) {
                throw new RestXmlFormatException($elementType . ' cannot have any children!');
            }
        } else {
            foreach ($element as $childType => $childData) {
                if (!in_array($childType, $handler::NESTABLES)) {
                    throw new RestXmlFormatException(($childType ?: '<unnamed>') . ' is not nestable inside ' . $elementType);
                }
            }
        }

        if (!$session->outbound && !$session->answered && !$session->preAnswer && !$handler::NO_ANSWER) {
            $this->app->outboundServer->logger->debug("Answering because Element {$elementType} need it");

            $promise = $session->client->sendMsg(
                (new ESL\Request\SendMsg)
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'answer')
                    ->setHeader('event-lock', 'true')
            )->then(function () use ($session) {
                $session->answered = true;
                $session->status = StatusEnum::InProgress;

                return resolve();
            });
        } else {
            $promise = resolve();
        }

        return $promise
            ->then(function () use ($session, $handler, $element): PromiseInterface {
                assert($element instanceof RestXmlElement);

                return $handler->execute($session, $element);
            });
    }

    /**
     * Fires a HTTP callback (generated by a Session event)
     *
     * @param Session $session
     * @param string $url
     * @param string $method
     * @param array<string, mixed> $params
     *
     * @return PromiseInterface
     */
    public function fireCallback(Session $session, string $url, string $method = 'POST', array $params = []): PromiseInterface
    {
        $params = array_merge($session->getPayload(), $params);

        return $this->app->httpClient->makeRequest($url, $method, $params)
            ->then(function () use ($method, $url, $params): PromiseInterface {
                $this->app->outboundServer->logger->info("Sent to {$method} {$url} with " . json_encode($params));

                return resolve();
            })
            ->otherwise(function (\Throwable $t) {
                $t = $t->getPrevious() ?: $t;

                $this->app->outboundServer->logger->error('Callback failure: ' . $t->getMessage(), [
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ]);
            });
    }

    public function onEvent(Session $session, Event $event): void
    {
        $logger = $this->app->outboundServer->logger;

        if (!isset($event->{'Event-Name'})) {
            return;
        }

        switch ($event->{'Event-Name'}) {
            case EventEnum::CHANNEL_EXECUTE_COMPLETE->value:
                if (in_array($event->Application, self::WAIT_FOR_APPLICATIONS)) {
                    $transferProgress = "variable_{$this->app->config->appPrefix}_transfer_progress";

                    if (isset($event->{$transferProgress}) && ($event->{$transferProgress} === 'true')) {
                        $this->pushToEventQueue($session);
                    } else {
                        $this->pushToEventQueue($session, $event);
                    }
                }
                break;

            case EventEnum::CHANNEL_HANGUP_COMPLETE->value:
                $session->hangupCause = HangupCauseEnum::from($event->{'Hangup-Cause'});
                $session->status = StatusEnum::Completed;

                $logger->info("Event: channel {$session->uuid} has hung up ({$event->{'Hangup-Cause'}})");
                $this->pushToEventQueue($session);
                break;

            case EventEnum::CHANNEL_BRIDGE->value:
            case EventEnum::CHANNEL_UNBRIDGE->value:
                if ($session->currentElement === Dial\Handler::ELEMENT_TYPE) {
                    $this->pushToEventQueue($session, $event);
                }
                break;

            case EventEnum::DETECTED_SPEECH->value:
                if ($session->currentElement === GetSpeech\Handler::ELEMENT_TYPE) {
                    $this->pushToEventQueue($session, $event);
                }
                break;

            case EventEnum::CUSTOM->value:
                if (!empty($session->currentElement)) {
                    switch ($session->currentElement) {
                        case Conference\Handler::ELEMENT_TYPE:
                            if (($event->{'Event-Subclass'} === 'conference::maintenance') && ($event->{'Unique-ID'} === $session->uuid)) {
                                switch ($event->Action) {
                                    case 'add-member':
                                        $logger->debug('Entered Conference');
                                        $this->pushToEventQueue($session, $event);
                                        break;

                                    case 'kick':
                                        if (isset($event->{'Conference-Name'}, $event->{'Member-ID'})) {
                                            $session->client->bgApi(
                                                (new ESL\Request\BgApi)
                                                    ->setParameters("conference {$event->{'Conference-Name'}} kick {$event->{'Member-ID'}}")
                                            );
                                            $logger->warning("Conference Room {$event->{'Conference-Name'}}, member {$event->{'Member-ID'}} pressed '*', kicked now!");
                                        }
                                        break;

                                    case 'digits-match':
                                        $logger->debug('Digits match on Conference');

                                        if (isset($event->{'Callback-Url'}, $event->{'Callback-Method'})) {
                                            $params = [
                                                'ConferenceMemberID' => isset($event->{'Member-ID'}) ? $event->{'Member-ID'} : '',
                                                'ConferenceUUID' => isset($event->{'Conference-Unique-ID'}) ? $event->{'Conference-Unique-ID'} : '',
                                                'ConferenceName' => isset($event->{'Conference-Name'}) ? $event->{'Conference-Name'} : '',
                                                'ConferenceDigitsMatch' => isset($event->{'Digits-Match'}) ? $event->{'Digits-Match'} : '',
                                                'ConferenceAction' => 'digits',
                                            ];

                                            $this->app->outboundServer->controller->fireCallback(
                                                $session, $event->{'Callback-Url'}, $event->{'Callback-Method'}, $params
                                            );
                                        }
                                        break;

                                    case 'floor-change':
                                        if (isset($event->Speak) && ($event->Speak === 'true')) {
                                            $this->pushToEventQueue($session, $event);
                                        }
                                        break;
                                }
                            }
                            break;

                        case Dial\Handler::ELEMENT_TYPE:
                            if (
                                ($event->{'Event-Subclass'} === $this->app->config->appPrefix . '::dial') &&
                                ($event->{'Unique-ID'} === $session->uuid) &&
                                ($event->Action === 'digits-match')
                            ) {
                                $logger->debug('Digits match on Dial');

                                if (isset($event->{'Callback-Url'}, $event->{'Callback-Method'})) {
                                    $params = [
                                        'DialDigitsMatch' => isset($event->{'Digits-Match'}) ? $event->{'Digits-Match'} : '',
                                        'DialAction' => 'digits',
                                        'DialALegUUID' => isset($event->{'Unique-ID'}) ? $event->{'Unique-ID'} : '',
                                        'DialBLegUUID' => isset($event->{'variable_bridge_uuid'}) ? $event->{'variable_bridge_uuid'} : '',
                                    ];

                                    $this->app->outboundServer->controller->fireCallback(
                                        $session, $event->{'Callback-Url'}, $event->{'Callback-Method'}, $params
                                    );
                                }
                            }
                            break;
                    }
                }
                break;


        }
    }

    public function waitForEvent(Session $session, int $timeout = 3600, bool $raiseExceptionOnHangup = false): PromiseInterface
    {
        $session->raiseExceptionOnHangup = $raiseExceptionOnHangup;

        $this->app->outboundServer->logger->debug('wait for action start');

        if (!empty($session->eventQueue)) {
            $event = array_shift($session->eventQueue);

            $this->app->outboundServer->logger->debug('wait for action end ' . json_encode($event));

            if (isset($session->hangupCause) && $session->raiseExceptionOnHangup)  {
                $this->app->outboundServer->logger->warning('wait for action call hung up!');

                throw new HangupException;
            }

            return resolve($event);
        }

        $session->eventQueueDeferred = new Deferred;
        $session->eventQueueTimer = Loop::addTimer($timeout, function() use ($session): void {
            $deferred = $session->eventQueueDeferred;
            unset($session->eventQueueDeferred);

            if (isset($session->hangupCause) && $session->raiseExceptionOnHangup)  {
                $this->app->outboundServer->logger->warning('wait for action call hung up!');
                $deferred->reject(new HangupException);
            } else {
                $this->app->outboundServer->logger->debug('wait for action end timed out!');
                $deferred->resolve();
            }
        });

        return $session->eventQueueDeferred->promise();
    }

    public function pushToEventQueue(Session $session, ?Event $event = null): void
    {
        if (isset($session->eventQueueDeferred)) {
            $this->app->outboundServer->logger->debug('wait for action end ' . json_encode($event));

            if (isset($session->eventQueueTimer)) {
                Loop::cancelTimer($session->eventQueueTimer);
                unset($session->eventQueueTimer);
            }

            $deferred = $session->eventQueueDeferred;

            unset($session->eventQueueDeferred);

            if (isset($session->hangupCause) && $session->raiseExceptionOnHangup)  {
                $this->app->outboundServer->logger->warning('wait for action call hung up!');

                throw new HangupException;
            }

            $deferred->resolve($event);
        } else {
            $session->eventQueue[] = $event;
        }
    }

    /**
     * Builds a playback array (to be concatenated for file_string://)
     *
     * @param Session $session
     * @param RestXmlElement $restXml
     * @param list<string> $elements
     *
     * @return list<string>
     */
    public function buildPlaybackArray(Session $session, RestXmlElement $restXml, array $elements): array
    {
        $soundFiles = [];

        foreach ($restXml as $element) {
            $elementType = $element->getName();

            if (!in_array($elementType, $elements)) {
                continue;
            }

            assert($element instanceof RestXmlElement);

            switch ($elementType) {
                case Play\Handler::ELEMENT_TYPE:
                    $playContext = $this->app->outboundServer->handlers[Play\Handler::ELEMENT_TYPE]->fetchContext($session, $element);

                    assert($playContext instanceof Play\Context);

                    for ($i = 0; $i < $playContext->loop; $i++) {
                        $soundFiles[] = $playContext->url;
                    }

                    if ($playContext->loop === Play\Handler::MAX_LOOPS) {
                        break 2;
                    }
                    break;

                case Speak\Handler::ELEMENT_TYPE:
                    /** @var Speak\Context */
                    $speakContext = $this->app->outboundServer->handlers[Speak\Handler::ELEMENT_TYPE]->fetchContext($session, $element);

                    assert($speakContext instanceof Speak\Context);

                    $speakContext->text = str_replace("'", "\\'", $speakContext->text);

                    if (isset($speakContext->type) && isset($speakContext->method)) {
                        $sayStr = "\${say_string} {$speakContext->language}.wav {$speakContext->language}"
                            . "{$speakContext->type} {$speakContext->method} '{$speakContext->text}'";
                    } else {
                        $sayStr = "say:{$speakContext->engine}:{$speakContext->voice}:'{$speakContext->text}'";
                    }

                    for ($i = 0; $i < $speakContext->loop; $i++) {
                        $soundFiles[] = $sayStr;
                    }
                    break;

                case Wait\Handler::ELEMENT_TYPE:
                    $waitContext = $this->app->outboundServer->handlers[Wait\Handler::ELEMENT_TYPE]->fetchContext($session, $element);

                    assert($waitContext instanceof Wait\Context);

                    $soundFiles[] = 'file_string://silence_stream://' . ($waitContext->length * 1000);
                    break;
            }
        }

        return $soundFiles;
    }

    public function getVariable(Session $session, string $variable): PromiseInterface
    {
        return $session->client->api(
            (new ESL\Request\Api)->setParameters("uuid_getvar {$session->uuid} {$variable}")
        )
            ->then(function(ESL\Response $response): PromiseInterface {
                if (!($response instanceof ESL\Response\ApiResponse)) {
                    return resolve();
                }

                $body = $response->getBody();

                if (empty($body)) {
                    return resolve();
                }

                if (($body === '_undef_') || (strpos($body, '-ERR') === 0)) {
                    return resolve();
                }

                return resolve($body);
            });
    }

    protected function hangup(Session $session): PromiseInterface
    {
        return $session->client->sendMsg(
            (new ESL\Request\SendMsg)
                ->setHeader('call-command', 'execute')
                ->setHeader('execute-app-name', 'hangup')
                ->setHeader('execute-app-arg', !isset($session->hangupCause) ? '' : $session->hangupCause->value)
                ->setHeader('event-lock', 'true')
        )
            ->then(function (ESL\Response\CommandReply $response): PromiseInterface {
                return resolve($response->isSuccessful());
            });
    }

    protected function disconnect(Session $session): void
    {
        $session->client->close();
    }
}
