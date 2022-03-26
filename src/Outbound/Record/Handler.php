<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\Record;

use RTCKit\Eqivo\{
    HangupCauseEnum,
    Session
};
use RTCKit\Eqivo\Exception\RestXmlFormatException;
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
use stdClass as Event;
use function React\Promise\{
    all,
    resolve
};

class Handler implements HandlerInterface
{
    use HandlerTrait;

    public const ELEMENT_TYPE = 'Record';

    public const RECORD_FILE_FORMATS = ['wav', 'mp3'];

    public const DEFAULT_RECORD_FORMAT = 'mp3';

    public const ALLOWED_METHODS = ['GET', 'POST'];

    public function execute(Session $session, RestXmlElement $element): PromiseInterface
    {
        $context = $this->fetchContext($session, $element);
        $deferred = new Deferred();

        if (!strlen($context->fileName)) {
            $context->fileName = date('Ymd-His') . '_' . $session->uuid;
        }

        $context->recordFile = "{$context->filePath}{$context->fileName}.{$context->fileFormat}";

        if ($context->bothLegs) {
            all([
                $context->session->client->sendMsg(
                    (new ESL\Request\SendMsg)
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'set')
                        ->setHeader('execute-app-arg', 'RECORD_STEREO=true')
                        ->setHeader('event-lock', 'true')
                ),
                $context->session->client->api(
                    (new ESL\Request\Api)
                        ->setParameters("uuid_record {$session->uuid} start {$context->recordFile}")
                ),
                $context->session->client->api(
                    (new ESL\Request\Api)
                        ->setParameters("sched_api +{$context->maxLength} none uuid_record {$session->uuid} stop {$context->recordFile}")
                ),
            ])
                ->then(function () use ($context, $deferred): PromiseInterface {
                    $this->app->outboundServer->logger->info('Record Both Executed');

                    return $this->fireCallback($context, $deferred);
                });
        } else {
            $promise = resolve();

            if ($context->playBeep) {
                $promise = $context->session->client->sendMsg(
                    (new ESL\Request\SendMsg)
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'playback')
                        ->setHeader('execute-app-arg', 'tone_stream://%(300,200,700)')
                        ->setHeader('event-lock', 'true')
                )
                    ->then(function () use ($context): PromiseInterface {
                        return $this->app->outboundServer->controller->waitForEvent($context->session);
                    })
                    ->then(function (Event $event): PromiseInterface {
                        $this->app->outboundServer->logger->debug("Record Beep played ({$event->{'Application-Response'}})");

                        return resolve();
                    });
            }

            $promise
                ->then(function () use ($context): PromiseInterface {
                    return $context->session->client->sendMsg(
                        (new ESL\Request\SendMsg)
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'start_dtmf')
                            ->setHeader('event-lock', 'true')
                    );
                })
                ->then(function () use ($context): PromiseInterface {
                    $this->app->outboundServer->logger->info('Record Started');

                    return all([
                        $context->session->client->sendMsg(
                            (new ESL\Request\SendMsg)
                                ->setHeader('call-command', 'execute')
                                ->setHeader('execute-app-name', 'set')
                                ->setHeader('execute-app-arg', 'playback_terminators=' . $context->finishOnKey)
                                ->setHeader('event-lock', 'true')
                        ),
                        $context->session->client->sendMsg(
                            (new ESL\Request\SendMsg)
                                ->setHeader('call-command', 'execute')
                                ->setHeader('execute-app-name', 'record')
                                ->setHeader('execute-app-arg', "{$context->recordFile} {$context->maxLength} {$context->silenceThreshold} {$context->timeout}")
                                ->setHeader('event-lock', 'true')
                        ),
                    ]);
                })
                ->then(function () use ($context): PromiseInterface {
                    return $this->app->outboundServer->controller->waitForEvent($context->session);
                })
                ->then(function (Event $event) use ($context): PromiseInterface {
                    $context->event = $event;

                    return $context->session->client->sendMsg(
                        (new ESL\Request\SendMsg)
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'stop_dtmf')
                            ->setHeader('event-lock', 'true')
                    );
                })
                ->then(function () use ($context, $deferred): PromiseInterface {
                    $this->app->outboundServer->logger->info('Record Completed');

                    return $this->fireCallback($context, $deferred);
                });
        }

        return $deferred->promise();
    }

    public function fetchContext(Session $session, RestXmlElement $element): Context
    {
        $context = new Context;
        $context->session = $session;

        $attributes = $element->attributes();

        if (isset($attributes->maxLength)) {
            $context->maxLength = (int)$attributes->maxLength;

            if ($context->maxLength < 1) {
                throw new RestXmlFormatException("Record 'maxLength' must be a positive integer");
            }
        } else {
            $context->maxLength = 60;
        }

        /* silenceThreshold was not originally exposed in RestXML, now it is.*/
        $context->silenceThreshold = isset($attributes->silenceThreshold) ? (int)$attributes->silenceThreshold : 500;

        if (isset($attributes->timeout)) {
            $context->timeout = (int)$attributes->timeout;

            if ($context->timeout < 1) {
                throw new RestXmlFormatException("Record 'timeout' must be a positive integer");
            }
        } else {
            $context->timeout = 15;
        }

        $context->finishOnKey = isset($attributes->finishOnKey) ? (string)$attributes->finishOnKey : '1234567890*#';
        $context->filePath = isset($attributes->filePath) ? (rtrim((string)$attributes->filePath, '/') . '/') : '';
        $context->playBeep = isset($attributes->playBeep) ? ((string)$attributes->playBeep === 'true') : true;
        $context->fileFormat = isset($attributes->fileFormat) ? (string)$attributes->fileFormat : static::DEFAULT_RECORD_FORMAT;

        if (!in_array($context->fileFormat, static::RECORD_FILE_FORMATS)) {
            throw new RestXmlFormatException("Format must be '" . implode("', '", static::RECORD_FILE_FORMATS) . "'");
        }

        $context->fileName = isset($attributes->fileName) ? (string)$attributes->fileName : '';
        $context->bothLegs = isset($attributes->bothLegs) ? ((string)$attributes->bothLegs === 'true') : false;
        $context->redirect = isset($attributes->redirect) ? ((string)$attributes->redirect === 'true') : true;
        $context->action = isset($attributes->action) ? (string)$attributes->action : '';
        $context->method = isset($attributes->method) ? (string)$attributes->method : 'POST';

        if (isset($attributes->method) && !in_array($context->method, static::ALLOWED_METHODS)) {
            throw new RestXmlFormatException("method must be '" . implode("', '", static::ALLOWED_METHODS) . "'");
        }

        return $context;
    }

    protected function fireCallback(Context $context, Deferred $deferred): PromiseInterface
    {
        if (strlen($context->action) && filter_var($context->action, FILTER_VALIDATE_URL)) {
            $params = [
                'RecordingFileFormat' => $context->fileFormat,
                'RecordingFilePath' => $context->filePath,
                'RecordingFileName' => $context->fileName,
                'RecordFile' => $context->recordFile,
            ];

            if ($context->bothLegs) {
                $params['RecordingDuration'] = -1;
                $params['Digits'] = '';
            } else {
                if (isset($context->event, $context->event->variable_record_ms)) {
                    $params['RecordingDuration'] = (int)$context->event->variable_record_ms;
                } else {
                    $params['RecordingDuration'] = -1;
                }

                if (isset($context->event, $context->event->variable_playback_terminator_used)) {
                    $params['Digits'] = $context->event->variable_playback_terminator_used;
                } else {
                    $params['Digits'] = '';
                }
            }

            if ($context->redirect) {
                return $this->app->outboundServer->controller->fetchAndExecuteRestXml(
                    $context->session, $context->action, $context->method, $params
                )
                    ->then(function () use ($deferred) {
                        $deferred->resolve(true);
                    });
            } else {
                return $this->app->outboundServer->controller->fireCallback(
                    $context->session, $context->action, $context->method, $params
                )
                    ->then(function () use ($deferred) {
                        $deferred->resolve();
                    });
            }
        }

        $deferred->resolve();

        return resolve();
    }
}
