<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Inbound\Handler;

use RTCKit\Eqivo\{
    Core,
    EventEnum,
    HangupCauseEnum,
    StatusEnum
};

use stdClass as Event;

class SessionHeartbeat implements HandlerInterface
{
    use HandlerTrait;

    /** @var EventEnum */
    public const EVENT = EventEnum::SESSION_HEARTBEAT;

    public function execute(Core $core, Event $event): void
    {
        if (!isset($this->app->config->callHeartbeatUrl)) {
            return;
        }

        $session = $core->getSession($event->{'Unique-ID'});

        if (!isset($session, $session)) {
            return;
        }

        $params = [
            'AnsweredTime' => (float)$event->{'Caller-Channel-Answered-Time'} / 1000000,
            'HeartbeatTime' => (float)$event->{'Event-Date-Timestamp'} / 1000000,
        ];
        $params['ElapsedTime'] = $params['HeartbeatTime'] - $params['AnsweredTime'];

        $this->app->inboundServer->controller->fireSessionCallback($session, $this->app->config->callHeartbeatUrl, 'POST', $params);
    }
}
