<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\Dial;

use Ramsey\Uuid\Uuid;
use function React\Promise\{
    all,
    resolve
};
use React\Promise\{
    Deferred,
    PromiseInterface
};

use RTCKit\ESL;
use RTCKit\Eqivo\Signal\Channel\Bridge as BridgeSignal;
use RTCKit\FiCore\Plan\{
    AbstractElement,
    HandlerInterface,
    HandlerTrait,
    Play,
    Speak,
    Wait
};
use RTCKit\FiCore\Switch\{
    Channel,
    EventEnum,
    HangupCauseEnum,
    ScheduledHangup,
    StatusEnum,
};

use stdClass as Event;

class Handler implements HandlerInterface
{
    use HandlerTrait;

    /** @var int */
    public const EVENT_TIMEOUT = 30;

    public function execute(Channel $channel, AbstractElement $element): PromiseInterface
    {
        assert($element instanceof Element);

        if (empty($element->numbers)) {
            $this->app->planConsumer->logger->error('Dial Aborted, No Number to dial!');

            return resolve(null);
        }

        if ($element->timeout > 0) {
            $element->setVars[] = 'call_timeout=' . $element->timeout;
        } else {
            $element->unsetVars[] = 'call_timeout';
        }

        if ($element->callerId == 'none') {
            $element->setVars[] = "effective_caller_id_number=''";
        } else {
            $element->setVars[] = 'effective_caller_id_number=' . $element->callerId;
        }

        if ($element->callerName == 'none') {
            $element->setVars[] = "effective_caller_id_name=''";
        } else {
            $element->setVars[] = 'effective_caller_id_name=' . $element->callerName;
        }

        $element->setVars[] = 'continue_on_fail=true';
        $element->setVars[] = 'hangup_after_bridge=false';
        $element->setVars[] = $this->app->config->appPrefix . '_dial_rang=false';

        $dialConfirm = '';

        if (isset($element->confirmSounds[0])) {
            $playStr = 'file_string://silence_stream://1!' . implode('!', $element->confirmSounds);

            if (isset($element->confirmKey[0])) {
                $confirmMusicStr = 'group_confirm_file=' . $playStr;
                $confirmKeyStr = 'group_confirm_key=' . $element->confirmKey;
            } else {
                $confirmMusicStr = 'group_confirm_file= playback' . $playStr;
                $confirmKeyStr = 'group_confirm_key=exec';
            }

            $dialConfirm = ",{$confirmMusicStr},{$confirmKeyStr},group_confirm_cancel_timeout=1,playback_delimiter=!";
        }

        $element->schedHangupId = Uuid::uuid4()->toString();
        $schedHup = new ScheduledHangup();
        $schedHup->uuid = $element->schedHangupId;
        $schedHup->timeout = $element->timeLimit;

        $element->channel->core->addScheduledHangup($schedHup);

        $ringFlag = "api_on_ring='uuid_setvar {$element->channel->uuid} {$this->app->config->appPrefix}_dial_rang true',api_on_pre_answer="
            . "'uuid_setvar {$element->channel->uuid} {$this->app->config->appPrefix}_dial_rang true'";

        $dialTimeLimit = "api_on_answer_1='sched_api +{$element->timeLimit} " . $element->schedHangupId
            . " uuid_transfer {$element->channel->uuid} -bleg hangup:" . HangupCauseEnum::ALLOTTED_TIMEOUT->value . " inline'";

        $element->dialStr = "<{$ringFlag},{$dialTimeLimit}{$dialConfirm}>{$element->dialStr}";

        if (count($element->numbers) < 2) {
            $element->dialStr .= ':_:';
        }

        if ($element->hangupOnStar) {
            $element->setVars[] = 'bridge_terminate_key=*';
        } else {
            $element->unsetVars[] = 'bridge_terminate_key';
        }

        if (isset($element->dialMusic[0])) {
            $element->setVars[] = 'playback_delimiter=!';
            $playStr = 'file_string://silence_stream://1!' . implode('!', $element->dialMusic);
            $element->setVars[] = 'bridge_early_media=false';
            $element->setVars[] = 'instant_ringback=true';
            $element->setVars[] = 'ringback=' . $playStr;
        }

        $this->app->planConsumer->logger->info('Dial Started ' . $element->dialStr);

        $promises = [
            'set' => $element->channel->client->sendMsg(
                (new ESL\Request\SendMsg())
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'multiset')
                    ->setHeader('execute-app-arg', implode(' ', $element->setVars))
                    ->setHeader('event-lock', 'true')
            ),
        ];

        foreach ($element->unsetVars as $var) {
            $promises[$var] = $element->channel->client->sendMsg(
                (new ESL\Request\SendMsg())
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'unset')
                    ->setHeader('execute-app-arg', $var)
                    ->setHeader('event-lock', 'true')
            );
        }

        $promises[] = $element->channel->client->sendMsg(
            (new ESL\Request\SendMsg())
                ->setHeader('call-command', 'execute')
                ->setHeader('execute-app-name', 'ring_ready')
                ->setHeader('event-lock', 'true')
        );

        return all($promises)
            ->then(function () use ($element): PromiseInterface {
                return $element->channel->client->sendMsg(
                    (new ESL\Request\SendMsg())
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'bridge')
                        ->setHeader('execute-app-arg', $element->dialStr)
                        ->setHeader('event-lock', 'false')
                );
            })
            ->then(function () use ($element): PromiseInterface {
                $promises = [];

                if (isset($element->digitsMatch[0], $element->signalAttn)) {
                    $eventTemplate = "Event-Name=CUSTOM,Event-Subclass={$this->app->config->appPrefix}::dial,Action=digits-match,"
                        . "Unique-ID={$element->channel->uuid},Signal-Attn={$element->signalAttn}";
                    $digitRealm = "{$this->app->config->appPrefix}_bda_dial_{$element->channel->uuid}";
                    $matches = explode(',', $element->digitsMatch);

                    foreach ($matches as $match) {
                        $match = trim($match);

                        if (strlen($match)) {
                            $rawEvent = "{$eventTemplate},Digits-Match={$match}";
                            $args = "{$digitRealm},{$match},exec:event,'{$rawEvent}'";

                            $promises[] = $element->channel->client->sendMsg(
                                (new ESL\Request\SendMsg())
                                    ->setHeader('call-command', 'execute')
                                    ->setHeader('execute-app-name', 'bind_digit_action')
                                    ->setHeader('execute-app-arg', $args)
                                    ->setHeader('event-lock', 'true')
                            );
                        }
                    }

                    $promises[] = $element->channel->client->sendMsg(
                        (new ESL\Request\SendMsg())
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'digit_action_set_realm')
                            ->setHeader('execute-app-arg', $digitRealm)
                            ->setHeader('event-lock', 'true')
                    );
                }

                return all($promises)
                    ->then(function () use ($element) {
                        return $this->waitForEvent($element);
                    });
            })
            ->then(function (Event $event) use ($element): PromiseInterface {
                if ($event->{'Event-Name'} === EventEnum::CHANNEL_UNBRIDGE->value) {
                    $element->bLegUuid = isset($event->{'variable_bridge_uuid'}) ? $event->{'variable_bridge_uuid'} : '';

                    return $this->app->planConsumer->waitForEvent($element->channel, self::EVENT_TIMEOUT, true);
                }

                return resolve($event);
            })
            ->then(function (Event $event) use ($element): PromiseInterface {
                $element->event = $event;
                $hangupCause = null;
                $reason = null;
                $promises = [];

                if (isset($event->variable_originate_disposition)) {
                    $hangupCause = $event->variable_originate_disposition;
                    $element->hangupCause = HangupCauseEnum::from($hangupCause);

                    if ($hangupCause === HangupCauseEnum::ORIGINATOR_CANCEL->value) {
                        $reason = $hangupCause . ' (A leg)';
                    } else {
                        $reason = $hangupCause . ' (B leg)';
                    }
                }

                if (!isset($hangupCause) || ($hangupCause === HangupCauseEnum::SUCCESS->value)) {
                    if (isset($element->channel->hangupCause)) {
                        $reason = $element->channel->hangupCause->value . ' (A leg)';
                    } else {
                        $promises = [
                            'bridge_hangup_cause' => $this->app->planConsumer->getVariable($element->channel, 'bridge_hangup_cause'),
                            'hangup_cause' => $this->app->planConsumer->getVariable($element->channel, 'hangup_cause'),
                        ];
                    }
                }

                if (isset($reason)) {
                    return all(['reason' => $reason]);
                } else {
                    return all($promises);
                }
            })
            ->then(function (array $reasons) use ($element): PromiseInterface {
                $reason = null;

                if (isset($reasons['reason'])) {
                    $reason = $reasons['reason'];
                } else {
                    if (!empty($reasons['bridge_hangup_cause'])) {
                        $reason = $reasons['bridge_hangup_cause'] . ' (B leg)';
                    } elseif (!empty($reasons['hangup_cause'])) {
                        $reason = $reasons['hangup_cause'] . ' (A leg)';
                    }
                }

                if (!isset($reason)) {
                    $reason = HangupCauseEnum::NORMAL_CLEARING->value . ' (A leg)';
                }

                $this->app->planConsumer->logger->info('Dial Finished with reason: ' . $reason);

                return all([
                    'sched_del' => $element->channel->client->bgApi(
                        (new ESL\Request\BgApi())->setParameters("sched_del {$element->schedHangupId}")
                    ),
                    'dial_rang' => $element->channel->client->bgApi(
                        (new ESL\Request\BgApi())->setParameters("uuid_getvar {$element->channel->uuid} {$this->app->config->appPrefix}_dial_rang")
                    ),
                ]);
            })
            ->then(function (array $vars) use ($element): PromiseInterface {
                if (isset($element->onHangup[0])) {
                    $signal = new BridgeSignal();

                    if (isset($element->event)) {
                        $signal->timestamp = (int)$element->event->{'Event-Date-Timestamp'} / 1e6;
                        $signal->event = $element->event;
                    } else {
                        $signal->timestamp = microtime(true);
                    }

                    $signal->attn = $element->onHangup;
                    $signal->channel = $element->channel;
                    $signal->bridged = isset($element->bLegUuid) ? $element->bLegUuid : '';
                    $signal->rang = isset($vars['dial_rang']) && ($vars['dial_rang'] === 'true');
                    $signal->status = StatusEnum::Completed;
                    $signal->hangupCause = $element->hangupCause;

                    if ($element->redirect) {
                        return $this->app->planConsumer->consume($element->channel, $element->onHangup, $signal)
                            ->then(function () {
                                return true;
                            });
                    } else {
                        $this->app->signalProducer->produce($signal);
                    }
                }

                return resolve(null);
            });
    }

    protected function waitForEvent(Element $element): PromiseInterface
    {
        return $this->app->planConsumer->waitForEvent($element->channel, self::EVENT_TIMEOUT, true)
            ->then(function (?Event $event) use ($element): PromiseInterface {
                if (isset($event)) {
                    switch ($event->{'Event-Name'}) {
                        case EventEnum::CHANNEL_BRIDGE->value:
                            $this->app->planConsumer->logger->info('Dial bridged');
                            break;

                        case EventEnum::CHANNEL_UNBRIDGE->value:
                            $this->app->planConsumer->logger->info('Dial unbridged');
                            return resolve($event);

                        case EventEnum::CHANNEL_EXECUTE_COMPLETE->value:
                            $this->app->planConsumer->logger->info('Dial completed ' . json_encode($event));
                            return resolve($event);
                    }
                }

                return $this->waitForEvent($element);
            });
    }
}
