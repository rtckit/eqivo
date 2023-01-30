<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Signal\Channel;

use RTCKit\FiCore\Signal\Channel\DTMF as FiCoreDTMF;

class DTMF extends FiCoreDTMF
{
    public string $bridged;
}
