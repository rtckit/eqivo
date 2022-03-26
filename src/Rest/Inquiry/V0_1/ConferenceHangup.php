<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\Eqivo\Core;
use RTCKit\Eqivo\Rest\Inquiry\RequestFactoryTrait;

/**
 * @OA\Schema(
 *     schema="ConferenceHangupParameters",
 *     required={"ConferenceName", "MemberID"},
 * )
 */
class ConferenceHangup
{
    use RequestFactoryTrait;

    /**
     * @OA\Property(
     *     description="Name of the conference in question",
     *     example="Room402",
     * )
     */
    public string $ConferenceName;

    /**
     * @OA\Property(
     *     description="List of comma separated member IDs to be affected; `all` shorthand is available too.",
     *     example="13,42",
     * )
     */
    public string $MemberID;

    public Core $core;
}
