<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\Conference;

use RTCKit\Eqivo\{
    Conference,
    Session
};
use RTCKit\Eqivo\Exception\RestXmlFormatException;
use RTCKit\Eqivo\Outbound\{
    ContextInterface,
    HandlerInterface,
    HandlerTrait,
    Play,
    RestXmlElement,
    Wait
};

use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\ESL;
use stdClass as Event;
use function React\Promise\{
    all,
    resolve
};

class Handler implements HandlerInterface
{
    use HandlerTrait;

    public const ELEMENT_TYPE = 'Conference';

    public const DEFAULT_TIMELIMIT = 0;

    public const DEFAULT_MAXMEMBERS = 200;

    public const RECORD_FILE_FORMATS = ['wav', 'mp3'];

    public const ALLOWED_METHODS = ['GET', 'POST'];

    public const EVENT_TIMEOUT = 30;

    public function execute(Session $session, RestXmlElement $element): PromiseInterface
    {
        $context = $this->fetchContext($session, $element);
        assert($context instanceof Context);

        return $this->prepareMoh($context)
            ->then(function (array $moh) use ($context): PromiseInterface {
                if (!empty($moh)) {
                    $context->setVars[] = 'playback_delimiter=!';
                    $context->setVars[] = 'conference_moh_sound=file_string://silence_stream://1!' . implode('!', $moh);
                } else {
                    $context->unsetVars[] = 'conference_moh_sound';
                }

                $promises = [
                    'set' => $context->session->client->sendMsg(
                        (new ESL\Request\SendMsg)
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'multiset')
                            ->setHeader('execute-app-arg', implode(' ', $context->setVars))
                            ->setHeader('event-lock', 'true')
                    ),
                ];

                foreach ($context->unsetVars as $var) {
                    $promises[$var] = $context->session->client->sendMsg(
                        (new ESL\Request\SendMsg)
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'unset')
                            ->setHeader('execute-app-arg', $var)
                            ->setHeader('event-lock', 'true')
                    );
                }

                return all($promises);
            })
            ->then(function () use ($context): PromiseInterface {
                if ($context->timeLimit > 0) {
                    $schedGroupName = 'conf_' . $context->room;

                    return $context->session->client->api(
                        (new ESL\Request\Api)->setParameters('sched_del ' . $schedGroupName)
                    )
                        ->then(function () use ($context, $schedGroupName): PromiseInterface {
                            $this->app->outboundServer->logger->warning("Conference: Room {$context->room}, timeLimit set to {$context->timeLimit} seconds");

                            return $context->session->client->api(
                                (new ESL\Request\Api)->setParameters(
                                    "sched_api +{$context->timeLimit} {$schedGroupName} conference {$context->room} kick all"
                                )
                            );
                        });
                }

                return resolve();
            })
            ->then(function () use ($context): PromiseInterface {
                $this->app->outboundServer->logger->info("Entering Conference: Room {$context->room} (flags $context->flags)");

                return $context->session->client->sendMsg(
                    (new ESL\Request\SendMsg)
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'conference')
                        ->setHeader('execute-app-arg', $context->fullRoom)
                );
            })
            ->then(function (ESL\Response\CommandReply $response) use ($context) {
                if (!$response->isSuccessful()) {
                    $this->app->outboundServer->logger->error("Conference: Entering Room {$context->room} Failed");
                }

                return $this->app->outboundServer->controller->waitForEvent($context->session);
            })
            ->then(function (?Event $event) use ($context): PromiseInterface {
                if (
                    isset($event, $event->{'Event-Subclass'}, $event->Action) &&
                    ($event->{'Event-Subclass'} === 'conference::maintenance') &&
                    ($event->Action === 'add-member')
                ) {
                    $context->confUuid = $event->{'Conference-Unique-ID'};
                    $context->memberId = (int)$event->{'Member-ID'};

                    $conference = $context->session->core->getConference($context->confUuid);

                    if (!isset($conference)) {
                        $conference = new Conference;
                        $conference->uuid = $context->confUuid;
                        $conference->room = $context->room;

                        $context->session->core->addConference($conference);
                    }

                    $this->app->outboundServer->logger->debug("Entered Conference: Room {$context->room} with Member-ID {$context->memberId}");

                    $hasFloor = isset($event->Floor) ? ($event->Floor === 'true') : false;
                    $canSpeak = isset($event->Speak) ? ($event->Speak === 'true') : false;
                    $isFirst = isset($event->{'Conference-Size'}) ? ($event->{'Conference-Size'} === '1') : false;

                    $this->fireCallback($context, 'enter');

                    if ($hasFloor && $canSpeak && $isFirst) {
                        $this->fireCallback($context, 'floor');
                    }

                    $promises = [];

                    if (!empty($context->digitsMatch) && !empty($context->callbackUrl)) {
                        $eventTemplate = 'Event-Name=CUSTOM,Event-Subclass=conference::maintenance,Action=digits-match' .
                            ',Unique-ID=' . $context->uuid .
                            ',Callback-Url=' . $context->callbackUrl .
                            ',Callback-Method=' . $context->callbackMethod .
                            ',Member-ID=' . $context->memberId .
                            ',Conference-Name=' . $context->room .
                            ',Conference-Unique-ID=' . $context->confUuid;
                        $context->digitRealm = "{$this->app->config->appPrefix}_bda_{$context->uuid}";

                        $matches = explode(',', $context->digitsMatch);
                        foreach ($matches as $match) {
                            $match = trim($match);

                            if (strlen($match)) {
                                $rawEvent = "{$eventTemplate},Digits-Match={$match}";
                                $args = "{$context->digitRealm},{$match},exec:event,'{$rawEvent}'";

                                $promises[] = $context->session->client->sendMsg(
                                    (new ESL\Request\SendMsg)
                                        ->setHeader('call-command', 'execute')
                                        ->setHeader('execute-app-name', 'bind_digit_action')
                                        ->setHeader('execute-app-arg', $args)
                                        ->setHeader('event-lock', 'true')
                                );
                            }
                        }
                    }

                    if ($context->hangupOnStar) {
                        $rawEvent = 'Event-Name=CUSTOM,Event-Subclass=conference::maintenance,Action=kick' .
                            ',Unique-ID=' . $context->uuid .
                            ',Member-ID=' . $context->memberId .
                            ',Conference-Name=' . $context->room .
                            ',Conference-Unique-ID=' . $context->confUuid;
                        $context->digitRealm = "{$this->app->config->appPrefix}_bda_{$context->uuid}";
                        $args = "{$context->digitRealm},*,exec:event,'{$rawEvent}'";

                        $promises[] = $context->session->client->sendMsg(
                            (new ESL\Request\SendMsg)
                                ->setHeader('call-command', 'execute')
                                ->setHeader('execute-app-name', 'bind_digit_action')
                                ->setHeader('execute-app-arg', $args)
                                ->setHeader('event-lock', 'true')
                        );
                    }

                    if (!empty($context->digitRealm)) {
                        $promises[] = $context->session->client->sendMsg(
                            (new ESL\Request\SendMsg)
                                ->setHeader('call-command', 'execute')
                                ->setHeader('execute-app-name', 'digit_action_set_realm')
                                ->setHeader('execute-app-arg', $context->digitRealm)
                                ->setHeader('event-lock', 'true')
                        );
                    }

                    if ($context->enterSound === 'beep:1') {
                        $promises[] = $context->session->bgApi(
                            (new ESL\Request\BgApi)->setParameters("conference {$context->room} play tone_stream://%%(300,200,700) async")
                        );
                    } else if ($context->enterSound === 'beep:2') {
                        $promises[] = $context->session->bgApi(
                            (new ESL\Request\BgApi)->setParameters("conference {$context->room} play tone_stream://L=2;%%(300,200,700) async")
                        );
                    }

                    if (!empty($context->recordFile)) {
                        $promises[] = $context->session->bgApi(
                            (new ESL\Request\BgApi)->setParameters("conference {$context->room} record {$context->recordFile}")
                        );

                        $this->app->outboundServer->logger->debug("Conference: Room {$context->room}, recording to file {$context->recordFile}");
                    }

                    $this->app->outboundServer->logger->debug("Conference: Room {$context->room}, waiting end ...");

                    return all($promises)
                        ->then(function () use ($context) {
                            return $this->waitForEvent($context);
                        });
                }

                return resolve();
            })
            ->then(function () use ($context) {
                if (isset($context->digitRealm)) {
                    $context->session->client->sendMsg(
                        (new ESL\Request\SendMsg)
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'clear_digit_action')
                            ->setHeader('execute-app-arg', $context->digitRealm)
                            ->setHeader('event-lock', 'true')
                    );
                }

                $this->fireCallback($context, 'exit');
                $this->app->outboundServer->logger->info("Leaving Conference: Room {$context->room}");

                if (isset($context->action) && filter_var($context->action, FILTER_VALIDATE_URL)) {
                    $params = $context->getPayload();

                    if (!empty($context->recordFile)) {
                        $params['RecordFile'] = $context->recordFile;
                    }

                    return $this->app->outboundServer->controller->fetchAndExecuteRestXml(
                        $context->session, $context->action, $context->method, $params
                    )
                        ->then(function () {
                            return resolve(true);
                        });
                }
            })
            ->otherwise(function (\Throwable $t) {
                $t = $t->getPrevious() ?: $t;

                $this->app->outboundServer->logger->error('Unhandled exception: ' . $t->getMessage(), [
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ]);
            });
    }

    public function fetchContext(Session $session, RestXmlElement $element): ContextInterface
    {
        $context = new Context;

        $context->session = $session;
        $context->room = trim((string)$element);

        if (!strlen($context->room)) {
            throw new RestXmlFormatException('Conference Room must be defined');
        }

        $attributes = $element->attributes();
        $context->fullRoom = "{$context->room}@{$this->app->config->appPrefix}";

        $context->mohSound = isset($attributes->waitSound) ? (string)$attributes->waitSound : '';
        $context->muted = isset($attributes->muted) ? ((string)$attributes->muted === 'true') : false;
        $context->startOnEnter = isset($attributes->startConferenceOnEnter) ? ((string)$attributes->startConferenceOnEnter === 'true') : true;
        $context->endOnExit = isset($attributes->endConferenceOnExit) ? ((string)$attributes->endConferenceOnExit === 'true') : false;
        $context->stayAlone = isset($attributes->stayAlone) ? ((string)$attributes->stayAlone === 'true') : true;
        $context->hangupOnStar = isset($attributes->hangupOnStar) ? ((string)$attributes->hangupOnStar === 'true') : false;
        $context->timeLimit = self::DEFAULT_TIMELIMIT;

        if (isset($attributes->timeLimit)) {
            $context->timeLimit = (int)$attributes->timeLimit;
        }

        if ($context->timeLimit <= 0) {
            $context->timeLimit = self::DEFAULT_TIMELIMIT;
        }

        $context->maxMembers = self::DEFAULT_MAXMEMBERS;

        if (isset($attributes->maxMembers)) {
            $context->maxMembers = (int)$attributes->maxMembers;
        }

        if (($context->maxMembers <= 0) || ($context->maxMembers > self::DEFAULT_MAXMEMBERS)) {
            $context->maxMembers = self::DEFAULT_MAXMEMBERS;
        }

        $context->enterSound = isset($attributes->enterSound) ? (string)$attributes->enterSound : '';
        $context->exitSound = isset($attributes->exitSound) ? (string)$attributes->exitSound : '';
        $context->recordFilePath = isset($attributes->recordFilePath) ? (string)$attributes->recordFilePath : '';
        $context->recordFileFormat = isset($attributes->recordFileFormat) ? (string)$attributes->recordFileFormat : 'mp3';

        if (!in_array($context->recordFileFormat, self::RECORD_FILE_FORMATS)) {
            throw new RestXmlFormatException("Format must be '" . implode("', '", self::RECORD_FILE_FORMATS) . "'");
        }

        $context->recordFileName = isset($attributes->recordFileName) ? (string)$attributes->recordFileName : '';

        if (!empty($context->recordFilePath)) {
            $context->recordFilePath = rtrim($context->recordFilePath, '/') . '/';
            $fileName = !empty($context->recordFileName) ? $context->recordFileName : date('Ymd-His') . '_' . $session->uuid;
            $context->recordFile = "{$context->recordFilePath}{$fileName}.{$context->recordFileFormat}";
        }

        $context->method = isset($attributes->method) ? (string)$attributes->method : 'POST';

        if (isset($attributes->method) && !in_array($context->method, self::ALLOWED_METHODS)) {
            throw new RestXmlFormatException("method must be '" . implode("', '", self::ALLOWED_METHODS) . "'");
        }

        $context->action = isset($attributes->action) ? (string)$attributes->action : '';
        $context->callbackUrl = isset($attributes->callbackUrl) ? (string)$attributes->callbackUrl : '';
        $context->callbackMethod = isset($attributes->callbackMethod) ? (string)$attributes->callbackMethod : 'POST';

        if (isset($attributes->callbackMethod) && !in_array($context->callbackMethod, self::ALLOWED_METHODS)) {
            throw new RestXmlFormatException("callbackMethod must be '" . implode("', '", self::ALLOWED_METHODS) . "'");
        }

        $context->digitsMatch = isset($attributes->digitsMatch) ? (string)$attributes->digitsMatch : '';
        $context->floorEvent = isset($attributes->floorEvent) ? ((string)$attributes->floorEvent === 'true') : false;

        $context->setVars[] = 'conference_controls=none';
        $context->setVars[] = 'conference_max_members=' . $context->maxMembers;

        $flags = [];

        if ($context->muted) {
            $flags[] = 'mute';
        }

        if ($context->startOnEnter) {
            $flags[] = 'moderator';
        }

        if (!$context->stayAlone) {
            $flags[] = 'mintwo';
        }

        if ($context->endOnExit) {
            $flags[] = 'endconf';
        }

        $context->flags = implode(',', $flags);

        if (!empty($context->flags)) {
            $context->setVars[] = 'conference_member_flags=' . $context->flags;
        } else {
            $context->unsetVars[] = 'conference_member_flags';
        }

        if ($context->exitSound === 'beep:1') {
            $context->setVars[] = 'conference_exit_sound=tone_stream://%%(300,200,700)';
        } else if ($context->exitSound === 'beep:2') {
            $context->setVars[] = 'conference_exit_sound=tone_stream://L=2;%%(300,200,700)';
        }

        return $context;
    }

    protected function prepareMoh(Context $context): PromiseInterface
    {
        if (empty($context->mohSound)) {
            return resolve([]);
        }

        $this->app->outboundServer->logger->info('Fetching remote sound from RestXML ' . $context->mohSound);

        return $this->app->outboundServer->controller->fetchRestXml($context->session, $context->mohSound)
            ->then(function (?RestXmlElement $restXml) use ($context): PromiseInterface {
                if (isset($restXml)) {
                    $moh = $this->app->outboundServer->controller->buildPlaybackArray(
                        $context->session, $restXml,
                        [Play\Handler::ELEMENT_TYPE, Wait\Handler::ELEMENT_TYPE]
                    );
                } else {
                    $moh = [];
                }

                return resolve($moh);
            });
    }

    protected function waitForEvent(Context $context): PromiseInterface
    {
        return $this->app->outboundServer->controller->waitForEvent($context->session, self::EVENT_TIMEOUT, true)
            ->then(function (?Event $event) use ($context): PromiseInterface {
                if (isset($event)) {
                    if (isset($event->Action) && ($event->Action === 'floor-change')) {
                        $this->fireCallback($context, 'floor');
                    } else {
                        return resolve();
                    }
                }

                return $this->waitForEvent($context);
            });
    }

    protected function fireCallback(Context $context, string $action): PromiseInterface
    {
        if (isset($context->callbackUrl[0]) && filter_var($context->callbackUrl, FILTER_VALIDATE_URL)) {
            $params = $context->getPayload();
            $params['ConferenceAction'] = $action;

            return $this->app->outboundServer->controller->fireCallback(
                $context->session, $context->callbackUrl, $context->callbackMethod, $params
            );
        }

        return resolve();
    }
}
