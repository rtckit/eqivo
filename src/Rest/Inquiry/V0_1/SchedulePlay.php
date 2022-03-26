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
 *     schema="SchedulePlayParameters",
 *     required={"CallUUID", "Sounds", "Time"},
 * )
 */
class SchedulePlay
{
    use RequestFactoryTrait;

    /**
     * @OA\Property(
     *     description="Unique identifier of the call to play media into",
     *     example="e69b32da-3243-4ba6-a965-5d2f64a57d48",
     * )
     */
    public string $CallUUID;

    /**
     * @OA\Property(
     *     description="Comma separated list of file paths/URIs to be played",
     *     example="/tmp/prompt.wav",
     * )
     */
    public string $Sounds;

    /**
     * @OA\Property(
     *     description="Time (in seconds) after which the media will be playedback",
     *     type="int",
     *     minimum=1,
     *     example=29,
     * )
     */
    public string|int $Time;

    /**
     * @OA\Property(
     *     description="Call leg(s) for which the media will be played; `aleg` refers to the initial call leg, `bleg` refers to the bridged call leg, if applicable.",
     *     example="both",
     *     enum={"aleg", "bleg", "both"},
     *     default=RTCKit\Eqivo\Rest\Controller\V0_1\SchedulePlay::DEFAULT_LEG,
     * )
     */
    public string $Legs;

    /**
     * @OA\Property(
     *     description="Maximum amount of time (in seconds) to playback the media",
     *     type="int",
     *     minimum=1,
     *     example=90,
     *     default=RTCKit\Eqivo\Rest\Controller\V0_1\SchedulePlay::DEFAULT_LENGTH,
     * )
     */
    public string|int $Length;

    /**
     * @OA\Property(
     *     description="Loops the media file(s) indefinitely",
     *     type="bool",
     *     example=true,
     *     default=false,
     * )
     */
    public string|bool $Loop;

    /**
     * @OA\Property(
     *     description="Whether the media should be mixed with the call's audio stream",
     *     type="bool",
     *     example=false,
     *     default=true,
     * )
     */
    public string|bool $Mix;

    public string $delimiter;

    /** @var list<string> */
    public array $soundList;

    public string $aLegFlags;

    public string $bLegFlags;

    public string $playStringALeg;

    public string $playStringBLeg;

    public Session $session;

    public Core $core;
}
