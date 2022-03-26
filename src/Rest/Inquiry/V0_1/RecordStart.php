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
 *     schema="RecordStartParameters",
 *     required={"ConferenceName"},
 * )
 */
class RecordStart
{
    use RequestFactoryTrait;

    /**
     * @OA\Property(
     *     description="Unique identifier of the call to be recorded",
     *     example="052d04e4-019a-45ff-a562-f74d4ae99ea2",
     * )
     */
    public string $CallUUID;

    /**
     * @OA\Property(
     *     description="File format (extension)",
     *     example="wav",
     *     enum=RTCKit\Eqivo\Rest\Controller\V0_1\RecordStart::RECORD_FILE_FORMATS,
     *     default=RTCKit\Eqivo\Rest\Controller\V0_1\RecordStart::DEFAULT_RECORD_FORMAT,
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
     *     example="sample_recording",
     *     default="",
     * )
     */
    public string $FileName;

    /**
     * @OA\Property(
     *     description="Maximum recording length, in seconds",
     *     type="int",
     *     minimum=1,
     *     example=89,
     *     default=RTCKit\Eqivo\Rest\Controller\V0_1\RecordStart::DEFAULT_TIME_LIMIT,
     * )
     */
    public string|int $TimeLimit;

    public Session $session;

    public Core $core;
}
