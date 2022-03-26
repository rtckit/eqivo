<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\Eqivo\{
    Core,
    ScheduledHangup
};
use RTCKit\Eqivo\Rest\Inquiry\RequestFactoryTrait;

/**
 * @OA\Schema(
 *     schema="CancelScheduledHangupParameters",
 *     required={"SchedHangupId"},
 * )
 */
class CancelScheduledHangup
{
    use RequestFactoryTrait;

    /**
     * @OA\Property(
     *     description="Unique identifier returned when scheduled hangup was originally requested",
     *     example="ea428fbd-ac9b-498c-8bb2-a36ac49f10fd",
     * )
     */
    public string $SchedHangupId;

    public ScheduledHangup $hup;

    public Core $core;
}
