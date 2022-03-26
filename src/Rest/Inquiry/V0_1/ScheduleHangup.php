<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\Eqivo\{
    Core,
    Session
};
use RTCKit\Eqivo\Rest\Inquiry\RequestFactoryTrait;

/**
 * @OA\Schema(
 *     schema="ScheduleHangupParameters",
 *     required={"CallUUID", "Time"},
 * )
 */
class ScheduleHangup
{
    use RequestFactoryTrait;

    /**
     * @OA\Property(
     *     description="Unique identifier of the call",
     *     example="f84fbadc-5df0-4c02-934b-aac0c1efb8ae",
     * )
     */
    public string $CallUUID;

    /**
     * @OA\Property(
     *     description="Time (in seconds) after which the call in question will be hung up",
     *     type="int",
     *     minimum=1,
     *     example=59,
     * )
     */
    public string|int $Time;

    public Session $session;

    public Core $core;
}
