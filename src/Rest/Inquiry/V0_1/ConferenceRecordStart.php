<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\Eqivo\Core;
use RTCKit\Eqivo\Rest\Inquiry\RequestFactoryTrait;

/**
 * @OA\Schema(
 *     schema="ConferenceRecordStartParameters",
 *     required={"ConferenceName"},
 * )
 */
class ConferenceRecordStart
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
     *     description="File format (extension)",
     *     example="wav",
     *     enum=RTCKit\Eqivo\Rest\Controller\V0_1\ConferenceRecordStart::RECORD_FILE_FORMATS,
     *     default=RTCKit\Eqivo\Rest\Controller\V0_1\ConferenceRecordStart::DEFAULT_RECORD_FORMAT,
     * )
     */
    public string $FileFormat;

    /**
     * @OA\Property(
     *     description="Directory path/URI where the recording file will be saved",
     *     example="/tmp/recordings",
     *     default="",
     * )
     */
    public string $FilePath;

    /**
     * @OA\Property(
     *     description="Recording file name (without extension); if empty, a timestamp based file name will be generated",
     *     example="Room402Rec",
     *     default="",
     * )
     */
    public string $FileName;

    public Core $core;
}
