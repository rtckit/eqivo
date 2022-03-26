<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Inbound\Handler;

use RTCKit\Eqivo\{
    Core,
    EventEnum
};

use stdClass as Event;

class RecordStop implements HandlerInterface
{
    use HandlerTrait;

    /** @var EventEnum */
    public const EVENT = EventEnum::RECORD_STOP;

    public function execute(Core $core, Event $event): void
    {
        if (!isset($this->app->config->recordUrl, $event->{'Record-File-Path'}, $event->{'Unique-ID'})) {
            return;
        }

        if ($event->{'Record-File-Path'} === 'all') {
            return;
        }

        $session = $core->getSession($event->{'Unique-ID'});

        if (!isset($session)) {
            return;
        }

        $params = [
            'RecordFile' => $event->{'Record-File-Path'},
            'RecordDuration' => isset($event->{'variable_record_seconds'}) ? (int)$event->{'variable_record_seconds'} : -1,
        ];

        $this->app->inboundServer->logger->info('Record Stop event ' . json_encode($params));
        $this->app->inboundServer->controller->fireSessionCallback($session, $this->app->config->recordUrl, $this->app->config->defaultHttpMethod, $params);
    }
}
