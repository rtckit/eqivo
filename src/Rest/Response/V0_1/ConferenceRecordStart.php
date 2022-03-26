<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

/**
 * @OA\Schema(
 *      schema="ConferenceRecordStartResponse",
 *      required={"Message", "RecordFile", "Success"},
 * )
 */
class ConferenceRecordStart
{
    public const MESSAGE_SUCCESS = 'Conference RecordStart Executed';

    public const MESSAGE_NO_CONFERENCE_NAME = 'ConferenceName Parameter must be present';

    public const MESSAGE_BAD_FILE_FORMAT = 'FileFormat Parameter must be';

    public const MESSAGE_FAILED = 'Conference RecordStart Failed';

    public const MESSAGE_NOT_FOUND = 'Conference RecordStart Failed -- Conference not found';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStart::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStart::MESSAGE_NO_CONFERENCE_NAME,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStart::MESSAGE_BAD_FILE_FORMAT,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStart::MESSAGE_FAILED,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStart::MESSAGE_NOT_FOUND,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStart::MESSAGE_SUCCESS
     * )
     */
    public string $Message;

    /**
     * Directory path/URI where the recording file will be saved
     *
     * @OA\Property(
     *     example="/tmp/recordings/sample.mp3",
     * )
     */
    public string $RecordFile;

    /**
     * Whether the request was successful or not
     *
     * @OA\Property(
     *      example=true
     * )
     */
    public bool $Success;
}
