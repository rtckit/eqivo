<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Command\Channel\SoundTouch;

use RTCKit\FiCore\Command\RequestInterface;
use RTCKit\FiCore\Switch\{
    Channel,
    DirectionEnum,
};

class Request implements RequestInterface
{
    public ActionEnum $action;

    public Channel $channel;

    public DirectionEnum $direction;

    public float $pitchSemiTones;

    public float $pitchOctaves;

    public float $pitch;

    public float $rate;

    public float $tempo;
}
