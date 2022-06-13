<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Inbound\Handler;

use RTCKit\Eqivo\{
    Core,
    EventEnum
};
use RTCKit\Eqivo\Rest\Controller\V0_1\Call;

use React\EventLoop\Loop;
use RTCKit\ESL;
use stdClass as Event;

class Custom implements HandlerInterface
{
    use HandlerTrait;

    /** @var EventEnum */
    public const EVENT = EventEnum::CUSTOM;

    public function execute(Core $core, Event $event): void
    {
        if (!isset($event->{'Event-Subclass'})) {
            return;
        }

        switch ($event->{'Event-Subclass'}) {
            case 'conference::maintenance':
                if (!isset($event->Action, $event->{'Conference-Unique-ID'})) {
                    return;
                }

                $conference = $core->getConference($event->{'Conference-Unique-ID'});

                if (!isset($conference)) {
                    return;
                }

                switch ($event->Action) {
                    case 'stop-recording':
                        if (!isset($this->app->config->recordUrl)) {
                            return;
                        }

                        $params = [
                            'RecordFile' => $event->Path,
                            'RecordDuration' => isset($event->{'Milliseconds-Elapsed'}) ? (int)$event->{'Milliseconds-Elapsed'} : -1,
                        ];

                        $this->app->inboundServer->logger->info('Conference Record Stop event ' . json_encode($params));
                        $this->app->inboundServer->controller->fireConferenceCallback($conference, $this->app->config->recordUrl, $this->app->config->defaultHttpMethod, $params);
                        return;

                    case 'conference-destroy':
                        $core->removeConference($event->{'Conference-Unique-ID'});
                        return;
                }

                return;

            case 'amd::info':
                $session = $core->getSession($event->{'Unique-ID'});

                if (!isset($session)) {
                    return;
                }

                $this->app->inboundServer->logger->info('AMD event ' . json_encode($event));
                $isAmdEvent = true;

                $session->amdDuration = (int)(((int)$event->variable_amd_result_microtime - (int)$event->{'Caller-Channel-Answered-Time'}) / 1000);
                $session->amdAnsweredBy = 'unknown';

                $result = $event->variable_amd_result ?? 'NOTSURE';
                $isMachine = false;

                switch ($result) {
                    case 'HUMAN':
                        $session->amdAnsweredBy = 'human';
                        break;

                    case 'MACHINE':
                        $isMachine = true;
                        $session->amdAnsweredBy = 'machine_start';
                        break;
                }

                $session->amdMethod = $event->{"variable_{$this->app->config->appPrefix}_amd_method"} ?? Call::DEFAULT_AMD_METHOD;
                $session->amdAsync = $event->{"variable_{$this->app->config->appPrefix}_amd_async"} === 'on';

                if ($session->amdAsync) {
                    $urlVar = "variable_{$this->app->config->appPrefix}_amd_url";

                    if (isset($event->{$urlVar})) {
                        $session->amdUrl = $event->{$urlVar};
                    }
                } else {
                    $session->amdUrl = $event->{"variable_{$this->app->config->appPrefix}_amd_target_url"};
                }

                if ($isMachine && isset($event->{"variable_{$this->app->config->appPrefix}_amd_msg_end"})) {
                    /* Kick off AVMD */
                    $this->app->inboundServer->logger->info('Activating AVMD (DetectMessageEnd enabled)');

                    $session->client->sendMsg(
                        (new ESL\Request\SendMsg)
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'avmd_start')
                    );

                    $timeout = (float)$event->{"variable_{$this->app->config->appPrefix}_amd_timeout"} - ($session->amdDuration / 1000);

                    $session->avmdTimer = Loop::addTimer($timeout, function() use ($session): void {
                        unset($session->avmdTimer);

                        $session->client->sendMsg(
                            (new ESL\Request\SendMsg)
                                ->setHeader('call-command', 'execute')
                                ->setHeader('execute-app-name', 'event')
                                ->setHeader('execute-app-arg', 'Event-Subclass=avmd::timeout,Event-Name=CUSTOM')
                        );
                    });

                    return;
                }

            case 'avmd::timeout':
            case 'avmd::beep':
                if (!isset($isAmdEvent)) {
                    $session = $core->getSession($event->{'Unique-ID'});

                    if (!isset($session)) {
                        return;
                    }

                    $this->app->inboundServer->logger->info('AVMD event ' . json_encode($event));

                    if (isset($session->avmdTimer)) {
                        Loop::cancelTimer($session->avmdTimer);
                        unset($session->avmdTimer);
                    }

                    $session->client->sendMsg(
                        (new ESL\Request\SendMsg)
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'avmd_stop')
                    );

                    $session->amdAnsweredBy = ($event->{'Event-Subclass'} === 'avmd::beep') ? 'machine_end_beep' : 'machine_end_other';
                }

                if (!isset($session)) {
                    return;
                }

                $method = $event->{"variable_{$this->app->config->appPrefix}_amd_method"} ?? Call::DEFAULT_AMD_METHOD;
                $params = [
                    'AnsweredBy' => $session->amdAnsweredBy,
                    'MachineDetectionDuration' => $session->amdDuration,
                ];

                if ($session->amdAsync) {
                    /* Asynchronous, just fire the callback, if configured */
                    if (isset($session->amdUrl)) {
                        $this->app->inboundServer->controller->fireSessionCallback($session, $session->amdUrl, $session->amdMethod, $params);
                    }
                } else {
                    /* Synchronous? Kick off call flow execution */
                    $this->app->outboundServer->controller->fetchAndExecuteRestXml($session, $session->amdUrl, $session->amdMethod, $params);
                }

                return;
        }
    }
}
