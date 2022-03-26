<?php

declare(strict_types=1);

namespace RTCKit\Eqivo;

enum ChannelStateEnum: string
{
    case CS_NEW = 'CS_NEW';
    case CS_INIT = 'CS_INIT';
    case CS_ROUTING = 'CS_ROUTING';
    case CS_SOFT_EXECUTE = 'CS_SOFT_EXECUTE';
    case CS_EXECUTE = 'CS_EXECUTE';
    case CS_EXCHANGE_MEDIA = 'CS_EXCHANGE_MEDIA';
    case CS_PARK = 'CS_PARK';
    case CS_CONSUME_MEDIA = 'CS_CONSUME_MEDIA';
    case CS_HIBERNATE = 'CS_HIBERNATE';
    case CS_RESET = 'CS_RESET';
    case CS_HANGUP = 'CS_HANGUP';
    case CS_REPORTING = 'CS_REPORTING';
    case CS_DESTROY = 'CS_DESTROY';
    case CS_NONE = 'CS_NONE';
}
