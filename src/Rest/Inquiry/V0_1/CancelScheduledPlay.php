<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\Eqivo\{
    Core,
    ScheduledPlay
};
use RTCKit\Eqivo\Rest\Inquiry\RequestFactoryTrait;

/**
 * @OA\Schema(
 *     schema="CancelScheduledPlayParameters",
 *     required={"SchedPlayId"},
 * )
 */
class CancelScheduledPlay
{
    use RequestFactoryTrait;

    /**
     * @OA\Property(
     *     description="Unique identifier returned when scheduled playback was originally requested",
     *     example="ea428fbd-ac9b-498c-8bb2-a36ac49f10fd",
     * )
     */
    public string $SchedPlayId;

    public ScheduledPlay $play;

    public Core $core;
}
