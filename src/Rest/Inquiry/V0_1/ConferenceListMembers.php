<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\Eqivo\Core;
use RTCKit\Eqivo\Rest\Inquiry\RequestFactoryTrait;

/**
 * @OA\Schema(
 *     schema="ConferenceListMembersParameters",
 *     required={"ConferenceName"},
 * )
 */
class ConferenceListMembers
{
    use RequestFactoryTrait;

    /**
     * @OA\Property(
     *     description="Name of the conference",
     *     example="Room402",
     * )
     */
    public string $ConferenceName;

    /**
     * @OA\Property(
     *     description="Restricts listed members to the provided values (comma separated member ID list)",
     *     example="13,42",
     * )
     */
    public string $MemberFilter;

    /**
     * @OA\Property(
     *     description="Restricts listed calls to the provided values (comma separated call UUID list)",
     *     example="872066e1-fd89-4c57-8733-93c113980bc9,55e4214a-604a-4b56-82e4-97834b0d524e",
     * )
     */
    public string $CallUUIDFilter;

    /**
     * @OA\Property(
     *     description="Restricts listed members to muted ones",
     *     type="bool",
     *     example="true",
     *     default="false",
     * )
     */
    public string $MutedFilter;

    /**
     * @OA\Property(
     *     description="Restricts listed members to deaf ones",
     *     type="bool",
     *     example="true",
     *     default="false",
     * )
     */
    public string $DeafFilter;

    public Core $core;
}
