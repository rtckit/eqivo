<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\Dial;

class Number
{
    public string $number;
    public string $extraDialString;
    public string $sendDigits;
    public bool $sendOnPreanswer;

    /** @var list<string> */
    public array $gateways = [];

    /** @var list<?string> */
    public array $gatewayCodecs = [];

    /** @var list<string> */
    public array $gatewayTimeouts = [];

    /** @var array<int, int|string> */
    public array $gatewayRetries = [];

    public string $dialStr;
}
