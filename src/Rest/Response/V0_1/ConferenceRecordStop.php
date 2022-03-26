<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

/**
 * @OA\Schema(
 *      schema="ConferenceRecordStopResponse",
 *      required={"Message", "RecordFile", "Success"},
 * )
 */
class ConferenceRecordStop
{
    public const MESSAGE_SUCCESS = 'Conference RecordStop Executed';

    public const MESSAGE_NO_CONFERENCE_NAME = 'ConferenceName Parameter must be present';

    public const MESSAGE_NO_RECORD_FILE = 'RecordFile Parameter must be present';

    public const MESSAGE_FAILED = 'Conference RecordStop Failed';

    public const MESSAGE_NOT_FOUND = 'Conference RecordStop Failed -- Conference not found';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStop::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStop::MESSAGE_NO_CONFERENCE_NAME,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStop::MESSAGE_NO_RECORD_FILE,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStop::MESSAGE_FAILED,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStop::MESSAGE_NOT_FOUND,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStop::MESSAGE_SUCCESS
     * )
     */
    public string $Message;

    /**
     * Whether the request was successful or not
     *
     * @OA\Property(
     *      example=true
     * )
     */
    public bool $Success;
}
