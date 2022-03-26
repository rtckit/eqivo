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
 *     schema="SoundTouchStopParameters",
 *     required={"CallUUID"},
 * )
 */
class SoundTouchStop
{
    use RequestFactoryTrait;

    /**
     * @OA\Property(
     *     description="Unique identifier of the call",
     *     example="fe372011-face-4bc2-bbcc-893d045bf67d",
     * )
     */
    public string $CallUUID;

    public Session $session;

    public Core $core;
}
