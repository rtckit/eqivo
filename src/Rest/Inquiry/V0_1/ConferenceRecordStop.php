<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\Eqivo\Core;
use RTCKit\Eqivo\Rest\Inquiry\RequestFactoryTrait;

/**
 * @OA\Schema(
 *     schema="ConferenceRecordStopParameters",
 *     required={"ConferenceName", "RecordFile"},
 * )
 */
class ConferenceRecordStop
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
     *     description="Full path to recording file, as returned by ConferenceRecordStart; `all` shorthand is also available",
     *     example="/tmp/recording/sample.wav",
     * )
     */
    public string $RecordFile;

    public Core $core;
}
