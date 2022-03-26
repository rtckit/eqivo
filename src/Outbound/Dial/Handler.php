<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\Dial;

use RTCKit\Eqivo\{
    EventEnum,
    HangupCauseEnum,
    ScheduledHangup,
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

use Ramsey\Uuid\Uuid;
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

    public const ELEMENT_TYPE = 'Dial';

    public const NO_ANSWER = true;

    public const NESTABLES = ['Number'];

    public const DEFAULT_TIMELIMIT = 14400;

    public const DEFAULT_TIMEOUT = -1;

    public const EVENT_TIMEOUT = 30;

    public function execute(Session $session, RestXmlElement $element): PromiseInterface
    {
        $context = $this->fetchContext($session, $element);

        if (empty($context->numbers)) {
            $this->app->outboundServer->logger->error('Dial Aborted, No Number to dial!');

            return resolve();
        }

        if ($context->timeout > 0) {
            $context->setVars[] = 'call_timeout=' . $context->timeout;
        } else {
            $context->unsetVars[] = 'call_timeout';
        }

        if ($context->callerId == 'none') {
            $context->setVars[] = "effective_caller_id_number=''";
        } else {
            $context->setVars[] = 'effective_caller_id_number=' . $context->callerId;
        }

        if ($context->callerName == 'none') {
            $context->setVars[] = "effective_caller_id_name=''";
        } else {
            $context->setVars[] = 'effective_caller_id_name=' . $context->callerName;
        }

        $context->setVars[] = 'continue_on_fail=true';
        $context->setVars[] = 'hangup_after_bridge=false';
        $context->setVars[] = $this->app->config->appPrefix . '_dial_rang=false';

        $promise = resolve([]);

        if (isset($context->confirmSound[0])) {
            $promise = $this->preparePlayString($context, $context->confirmSound);
        }

        return $promise
            ->then(function (array $confirmSounds) use ($context): PromiseInterface {
                $dialConfirm = '';

                if (isset($confirmSounds[0])) {
                    $playStr = 'file_string://silence_stream://1!' . implode('!', $confirmSounds);

                    if (isset($context->confirmKey[0])) {
                        $confirmMusicStr = 'group_confirm_file=' . $playStr;
                        $confirmKeyStr = 'group_confirm_key=' . $context->confirmKey;
                    } else {
                        $confirmMusicStr = 'group_confirm_file= playback' . $playStr;
                        $confirmKeyStr = 'group_confirm_key=exec';
                    }

                    $dialConfirm = ",{$confirmMusicStr},{$confirmKeyStr},group_confirm_cancel_timeout=1,playback_delimiter=!";
                }

                $context->schedHangupId = Uuid::uuid4()->toString();
                $schedHup = new ScheduledHangup;
                $schedHup->uuid = $context->schedHangupId;
                $schedHup->timeout = $context->timeLimit;

                $context->session->core->addScheduledHangup($schedHup);

                $ringFlag = "api_on_ring='uuid_setvar {$context->session->uuid} {$this->app->config->appPrefix}_dial_rang true',api_on_pre_answer="
                    . "'uuid_setvar {$context->session->uuid} {$this->app->config->appPrefix}_dial_rang true'";

                $dialTimeLimit = "api_on_answer_1='sched_api +{$context->timeLimit} " . $context->schedHangupId
                    . " uuid_transfer {$context->session->uuid} -bleg hangup:" . HangupCauseEnum::ALLOTTED_TIMEOUT->value . " inline'";

                $context->dialStr = "<{$ringFlag},{$dialTimeLimit}{$dialConfirm}>{$context->dialStr}";

                if (count($context->numbers) < 2) {
                    $context->dialStr .= ':_:';
                }

                if ($context->hangupOnStar) {
                    $context->setVars[] = 'bridge_terminate_key=*';
                } else {
                    $context->unsetVars[] = 'bridge_terminate_key';
                }

                $promise = resolve([]);

                if (!isset($context->dialMusic[0])) {
                    $context->setVars[] = 'bridge_early_media=false';
                    $context->setVars[] = 'instant_ringback=true';
                    $context->setVars[] = 'ringback=${us-ring}';
                } else if ($context->dialMusic === 'none') {
                    $context->setVars[] = 'bridge_early_media=false';
                    $context->unsetVars[] = 'instant_ringback';
                    $context->unsetVars[] = 'ringback';
                } else if ($context->dialMusic === 'real') {
                    $context->setVars[] = 'bridge_early_media=false';
                    $context->setVars[] = 'instant_ringback=false';
                    $context->unsetVars[] = 'ringback';
                } else {
                    $promise = $this->preparePlayString($context, $context->dialMusic);
                }

                return $promise;
            })
            ->then(function (array $ringbacks) use ($context): PromiseInterface {
                if (isset($ringbacks[0])) {
                    $context->setVars[] = 'playback_delimiter=!';
                    $playStr = 'file_string://silence_stream://1!' . implode('!', $ringbacks);
                    $context->setVars[] = 'bridge_early_media=false';
                    $context->setVars[] = 'instant_ringback=true';
                    $context->setVars[] = 'ringback=' . $playStr;
                }

                $this->app->outboundServer->logger->info('Dial Started ' . $context->dialStr);

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

                $promises[] = $context->session->client->sendMsg(
                    (new ESL\Request\SendMsg)
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'ring_ready')
                        ->setHeader('event-lock', 'true')
                );

                return all($promises);
            })
            ->then(function () use ($context): PromiseInterface {
                return $context->session->client->sendMsg(
                    (new ESL\Request\SendMsg)
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'bridge')
                        ->setHeader('execute-app-arg', $context->dialStr)
                        ->setHeader('event-lock', 'false')
                );
            })
            ->then(function () use ($context): PromiseInterface {
                $promises = [];

                if (isset($context->digitsMatch[0], $context->callbackUrl[0])) {
                    $eventTemplate = "Event-Name=CUSTOM,Event-Subclass={$this->app->config->appPrefix}::dial,Action=digits-match,"
                        . "Unique-ID={$context->session->uuid},Callback-Url={$context->callbackUrl},Callback-Method={$context->callbackMethod}";
                    $digitRealm = "{$this->app->config->appPrefix}_bda_dial_{$context->session->uuid}";
                    $matches = explode(',', $context->digitsMatch);

                    foreach ($matches as $match) {
                        $match = trim($match);

                        if (strlen($match)) {
                            $rawEvent = "{$eventTemplate},Digits-Match={$match}";
                            $args = "{$digitRealm},{$match},exec:event,'{$rawEvent}'";

                            $promises[] = $context->session->client->sendMsg(
                                (new ESL\Request\SendMsg)
                                    ->setHeader('call-command', 'execute')
                                    ->setHeader('execute-app-name', 'bind_digit_action')
                                    ->setHeader('execute-app-arg', $args)
                                    ->setHeader('event-lock', 'true')
                            );
                        }
                    }

                    $promises[] = $context->session->client->sendMsg(
                        (new ESL\Request\SendMsg)
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'digit_action_set_realm')
                            ->setHeader('execute-app-arg', $digitRealm)
                            ->setHeader('event-lock', 'true')
                    );
                }

                return all($promises)
                    ->then(function (array $args) use ($context) {
                        return $this->waitForEvent($context);
                    });
            })
            ->then(function (Event $event) use ($context): PromiseInterface {
                if ($event->{'Event-Name'} === EventEnum::CHANNEL_UNBRIDGE->value) {
                    $context->bLegUuid = isset($event->{'variable_bridge_uuid'}) ? $event->{'variable_bridge_uuid'} : '';

                    return $this->app->outboundServer->controller->waitForEvent($context->session, self::EVENT_TIMEOUT, true);
                }

                return resolve($event);
            })
            ->then(function (Event $event) use ($context): PromiseInterface {
                $hangupCause = null;
                $reason = null;
                $promises = [];

                if (isset($event->variable_originate_disposition)) {
                    $hangupCause = $context->hangupCause = $event->variable_originate_disposition;

                    if ($hangupCause === HangupCauseEnum::ORIGINATOR_CANCEL->value) {
                        $reason = $hangupCause . ' (A leg)';
                    } else {
                        $reason = $hangupCause . ' (B leg)';
                    }
                }

                if (!isset($hangupCause) || ($hangupCause === HangupCauseEnum::SUCCESS->value)) {
                    if (isset($context->session->hangupCause)) {
                        $reason = $context->session->hangupCause->value . ' (A leg)';
                    } else {
                        $promises = [
                            'bridge_hangup_cause' => $this->app->outboundServer->controller->getVariable($context->session, 'bridge_hangup_cause'),
                            'hangup_cause' => $this->app->outboundServer->controller->getVariable($context->session, 'hangup_cause'),
                        ];
                    }
                }

                if (isset($reason)) {
                    return all(['reason' => $reason]);
                } else {
                    return all($promises);
                }
            })
            ->then(function (array $reasons) use ($context): PromiseInterface {
                $reason = null;

                if (isset($reasons['reason'])) {
                    $reason = $reasons['reason'];
                } else {
                    if (!empty($reasons['bridge_hangup_cause'])) {
                        $reason = $reasons['bridge_hangup_cause'] . ' (B leg)';
                    } else if (!empty($reasons['hangup_cause'])) {
                        $reason = $reasons['hangup_cause'] . ' (A leg)';
                    }
                }

                if (!isset($reason)) {
                    $reason = HangupCauseEnum::NORMAL_CLEARING->value . ' (A leg)';
                }

                $this->app->outboundServer->logger->info('Dial Finished with reason: ' . $reason);

                return all([
                    'sched_del' => $context->session->client->bgApi(
                        (new ESL\Request\BgApi)->setParameters("sched_del {$context->schedHangupId}")
                    ),
                    'dial_rang' => $context->session->client->bgApi(
                        (new ESL\Request\BgApi)->setParameters("uuid_getvar {$context->session->uuid} {$this->app->config->appPrefix}_dial_rang")
                    ),
                ]);
            })
            ->then(function (array $vars) use ($context): PromiseInterface {
                if (isset($context->action[0]) && filter_var($context->action, FILTER_VALIDATE_URL)) {
                    $params = [
                        'DialHangupCause' => isset($context->hangupCause) ? $context->hangupCause : '',
                        'DialALegUUID' => $context->session->uuid,
                        'DialBLegUUID' => isset($context->bLegUuid) ? $context->bLegUuid : '',
                    ];

                    if (isset($vars['dial_rang']) && ($vars['dial_rang'] === 'true')) {
                        $params['DialRingStatus'] = 'true';
                    } else {
                        $params['DialRingStatus'] = 'false';
                    }

                    if ($context->redirect) {
                        return $this->app->outboundServer->controller->fetchAndExecuteRestXml(
                            $context->session, $context->action, $context->method, $params
                        )
                            ->then(function () {
                                return true;
                            });
                    } else {
                        return $this->app->outboundServer->controller->fireCallback(
                            $context->session, $context->action, $context->method, $params
                        );
                    }
                }

                return resolve();
            });
    }

    public function fetchContext(Session $session, RestXmlElement $element): Context
    {
        $context = new Context;
        $context->session = $session;

        $attributes = $element->attributes();

        $context->action = isset($attributes->action) ? (string)$attributes->action : '';

        if (isset($attributes->method)) {
            if (!in_array((string)$attributes->method, ['GET', 'POST'])) {
                throw new RestXmlAttributeException("method must be 'GET' or 'POST'");
            }

            $context->method = (string)$attributes->method;
        } else {
            $context->method = 'POST';
        }

        $context->callerId = isset($attributes->callerId) ? (string)$attributes->callerId : '';
        $context->callerName = isset($attributes->callerName) ? (string)$attributes->callerName : '';

        if (isset($attributes->timeLimit)) {
            $context->timeLimit = (int)$attributes->timeLimit;

            if ($context->timeLimit < 1) {
                $context->timeLimit = self::DEFAULT_TIMELIMIT;
            }
        } else {
            $context->timeLimit = self::DEFAULT_TIMELIMIT;
        }

        if (isset($attributes->timeout)) {
            $context->timeout = (int)$attributes->timeout;

            if ($context->timeout < 1) {
                $context->timeout = self::DEFAULT_TIMEOUT;
            }
        } else {
            $context->timeout = self::DEFAULT_TIMEOUT;
        }

        $context->confirmSound = isset($attributes->confirmSound) ? (string)$attributes->confirmSound : '';
        $context->confirmKey = isset($attributes->confirmKey) ? (string)$attributes->confirmKey : '';
        $context->dialMusic = isset($attributes->dialMusic) ? (string)$attributes->dialMusic : '';
        $context->hangupOnStar = isset($attributes->hangupOnStar) ? ((string)$attributes->hangupOnStar === 'true') : false;
        $context->redirect = isset($attributes->redirect) ? ((string)$attributes->redirect === 'true') : true;
        $context->callbackUrl = isset($attributes->callbackUrl) ? (string)$attributes->callbackUrl : '';

        if (isset($attributes->callbackMethod)) {
            if (!in_array((string)$attributes->callbackMethod, ['GET', 'POST'])) {
                throw new RestXmlAttributeException("callbackMethod must be 'GET' or 'POST'");
            }

            $context->callbackMethod = (string)$attributes->callbackMethod;
        } else {
            $context->callbackMethod = 'POST';
        }

        $context->digitsMatch = isset($attributes->digitsMatch) ? (string)$attributes->digitsMatch : '';

        $numDialStr = [];

        foreach ($element as $number) {
            $tuples = [];
            $entry = new Number;
            $entry->number = str_replace([',', '|'], '', trim((string)$number));

            if (!isset($entry->number[0])) {
                $this->app->outboundServer->logger->error('Number not defined on Number object');

                continue;
            }

            $attributes = $number->attributes();

            $entry->extraDialString = isset($attributes->extraDialString) ? (string)$attributes->extraDialString : '';
            $entry->sendDigits = isset($attributes->sendDigits) ? (string)$attributes->sendDigits : '';
            $entry->sendOnPreanswer = isset($attributes->sendOnPreanswer) ? ((string)$attributes->sendOnPreanswer === 'true') : true;
            $entry->gateways = explode(',', isset($attributes->gateways) ? (string)$attributes->gateways : '');
            $entry->gatewayCodecs = str_getcsv(isset($attributes->gatewayCodecs) ? (string)$attributes->gatewayCodecs : '', ',', "'");
            $entry->gatewayTimeouts = explode(',', isset($attributes->gatewayTimeouts) ? (string)$attributes->gatewayTimeouts : '');
            $entry->gatewayRetries = explode(',', isset($attributes->gatewayRetries) ? (string)$attributes->gatewayRetries : '');

            if (!isset($entry->gateways[0][0])) {
                $this->app->outboundServer->logger->error('Gateway not defined on Number object');

                continue;
            }

            $optionSendDigits = '';

            if (isset($entry->sendDigits[0])) {
                if ($entry->sendOnPreanswer) {
                    $optionSendDigits = "api_on_media='uuid_recv_dtmf \${uuid} ${$entry->sendDigits}'";
                } else {
                    $optionSendDigits = "api_on_answer_2='uuid_recv_dtmf \${uuid} ${$entry->sendDigits}'";
                }
            }

            foreach ($entry->gateways as $idx => $gateway) {
                $numOptions = [];

                if (isset($context->callbackUrl[0], $context->callbackMethod[0])) {
                    $numOptions[] = $this->app->config->appPrefix . '_dial_callback_url=' . $context->callbackUrl;
                    $numOptions[] = $this->app->config->appPrefix . '_dial_callback_method=' . $context->callbackMethod;
                    $numOptions[] = $this->app->config->appPrefix . '_dial_callback_aleg=' . $context->session->uuid;
                }

                if (isset($optionSendDigits[0])) {
                    $numOptions[] = $optionSendDigits;
                }

                if (isset($entry->gatewayCodecs[$idx][0])) {
                    $numOptions[] = "absolute_codec_string='{$entry->gatewayCodecs[$idx]}'";
                }

                if (isset($entry->gatewayTimeouts[$idx]) && ($entry->gatewayTimeouts[$idx] > 0)) {
                    $numOptions[] = 'leg_timeout=' . $entry->gatewayTimeouts[$idx];
                }

                if (!isset($entry->gatewayRetries[$idx]) || ((int)$entry->gatewayRetries[$idx] < 1)) {
                    $entry->gatewayRetries[$idx] = 1;
                }

                if (isset($entry->extraDialString[0])) {
                    $numOptions[] = $entry->extraDialString;
                }

                $options = '';

                if (isset($numOptions[0])) {
                    $options = '[' . implode(',', $numOptions) . ']';
                }

                $numStr = "{$options}{$gateway}{$entry->number}";

                if ((int)$entry->gatewayRetries[$idx] === 1) {
                    $tuples[] = $numStr;
                } else {
                    $tuples[] = $numStr . str_repeat('|' . $numStr, (int)$entry->gatewayRetries[$idx] - 1);
                }

            }

            $entry->dialStr = implode('|', $tuples);
            $numDialStr[] = $entry->dialStr;
            $context->numbers[] = $entry;
        }

        $context->dialStr = implode(':_:', $numDialStr);

        return $context;
    }

    protected function preparePlayString(Context $context, string $url): PromiseInterface
    {
        $this->app->outboundServer->logger->info('Fetching remote sound from RestXML ' . $url);

        return $this->app->outboundServer->controller->fetchRestXml($context->session, $url)
            ->then(function (RestXmlElement $restXml) use ($context, $url): PromiseInterface {
                $soundFiles = $this->app->outboundServer->controller->buildPlaybackArray(
                    $context->session, $restXml,
                    [Play\Handler::ELEMENT_TYPE, Speak\Handler::ELEMENT_TYPE, Wait\Handler::ELEMENT_TYPE]
                );

                $this->app->outboundServer->logger->info('Fetching remote sound from RestXML done for ' . $url);

                return resolve($soundFiles);
            });
    }

    protected function waitForEvent(Context $context): PromiseInterface
    {
        return $this->app->outboundServer->controller->waitForEvent($context->session, self::EVENT_TIMEOUT, true)
            ->then(function (?Event $event) use ($context): PromiseInterface {
                if (isset($event)) {
                    switch ($event->{'Event-Name'}) {
                        case EventEnum::CHANNEL_BRIDGE->value:
                            $this->app->outboundServer->logger->info('Dial bridged');
                            break;

                        case EventEnum::CHANNEL_UNBRIDGE->value:
                            $this->app->outboundServer->logger->info('Dial unbridged');
                            return resolve($event);

                        case EventEnum::CHANNEL_EXECUTE_COMPLETE->value:
                            $this->app->outboundServer->logger->info('Dial completed ' . json_encode($event));
                            return resolve($event);
                    }
                }

                return $this->waitForEvent($context);
            });
    }
}
