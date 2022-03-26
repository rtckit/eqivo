<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Inbound\Handler;

use RTCKit\Eqivo\{
    Core,
    EventEnum
};

use stdClass as Event;

class Custom implements HandlerInterface
{
    use HandlerTrait;

    /** @var EventEnum */
    public const EVENT = EventEnum::CUSTOM;

    public function execute(Core $core, Event $event): void
    {
        if (!isset($event->{'Event-Subclass'}, $event->Action, $event->{'Conference-Unique-ID'})) {
            return;
        }

        if ($event->{'Event-Subclass'} === 'conference::maintenance') {
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
        }
    }
}
