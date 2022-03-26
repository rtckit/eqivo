<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Inbound\Handler;

use RTCKit\Eqivo\{
    App,
    Core,
    EventEnum
};

use stdClass as Event;

interface HandlerInterface
{
    /** @var EventEnum */
    public const EVENT = EventEnum::ALL;

    public function setApp(App $app): static;

    public function execute(Core $core, Event $event): void;
}
