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
 *     schema="PlayStopParameters",
 *     required={"CallUUID"},
 * )
 */
class PlayStop
{
    use RequestFactoryTrait;

    /**
     * @OA\Property(
     *     description="Unique identifier of the call",
     *     example="441afb63-85bc-49d4-9ac8-8459f9bf5e6b",
     * )
     */
    public string $CallUUID;

    public Session $session;

    public Core $core;
}
