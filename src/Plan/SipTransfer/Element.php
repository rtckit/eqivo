<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\SipTransfer;

use RTCKit\FiCore\Plan\AbstractElement;

class Element extends AbstractElement
{
    /** @var list<string> */
    public array $uris = [];
}
