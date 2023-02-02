<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Command\Conference\Query;

use RTCKit\FiCore\Command\RequestInterface;
use RTCKit\FiCore\Switch\Conference;

class Request implements RequestInterface
{
    public ActionEnum $action;

    public Conference $conference;

    /** @var list<string> */
    public array $members = [];

    /** @var list<string> */
    public array $channels = [];

    public bool $muted;

    public bool $deaf;
}
