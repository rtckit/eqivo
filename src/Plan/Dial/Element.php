<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\Dial;

use RTCKit\FiCore\Plan\AbstractElement;
use RTCKit\FiCore\Switch\HangupCauseEnum;

use stdClass as Event;

class Element extends AbstractElement
{
    public string $onHangup;
    public bool $hangupOnStar;
    public string $callerId;
    public string $callerName;
    public int $timeLimit;
    public int $timeout;

    /** @var list<string> */
    public array $confirmSounds = [];
    public string $confirmKey;

    /** @var list<string> */
    public array $dialMusic = [];
    public bool $redirect;
    public string $signalAttn;
    public string $digitsMatch;

    /** @var list<Number> */
    public array $numbers = [];

    public string $dialStr;
    public string $bLegUuid;
    public string $schedHangupId;
    public HangupCauseEnum $hangupCause;

    /** @var list<string> */
    public array $setVars = [];

    /** @var list<string> */
    public array $unsetVars = [];

    public Event $event;
}
