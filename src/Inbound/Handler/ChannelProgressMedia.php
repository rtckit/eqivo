<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Inbound\Handler;

use RTCKit\Eqivo\EventEnum;

class ChannelProgressMedia extends ChannelProgress
{
    /** @var EventEnum */
    public const EVENT = EventEnum::CHANNEL_PROGRESS_MEDIA;
}
