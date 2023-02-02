<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Signal\Channel;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Switch\{
    Channel,
    HangupCauseEnum,
    StatusEnum,
};

class Bridge extends AbstractSignal
{
    public Channel $channel;

    public string $bridged;

    public StatusEnum $status;

    public HangupCauseEnum $hangupCause;

    public bool $rang;
}
