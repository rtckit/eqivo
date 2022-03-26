<?php

declare(strict_types=1);

namespace RTCKit\Eqivo;

enum StatusEnum: string
{
    case Ringing = 'ringing';
    case EarlyMedia = 'early-media';
    case Answer = 'answer';
    case InProgress = 'in-progress';
    case Completed = 'completed';
}
