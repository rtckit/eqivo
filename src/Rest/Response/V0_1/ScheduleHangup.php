<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

/**
 * @OA\Schema(
 *      schema="ScheduleHangupResponse",
 *      required={"Message", "Success", "SchedHangupId"},
 * )
 */
class ScheduleHangup
{
    public const MESSAGE_SUCCESS = 'ScheduleHangup Executed';

    public const MESSAGE_NO_CALLUUID = 'CallUUID Parameter must be present';

    public const MESSAGE_NO_TIME = 'Time Parameter must be present';

    public const MESSAGE_NEGATIVE_TIME = 'Time Parameter must be > 0!';

    public const MESSAGE_NOT_FOUND = 'ScheduleHangup Failed -- Call not found';

    public const MESSAGE_FAILED = 'ScheduleHangup Failed';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\ScheduleHangup::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ScheduleHangup::MESSAGE_NO_CALLUUID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ScheduleHangup::MESSAGE_NO_TIME,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ScheduleHangup::MESSAGE_NEGATIVE_TIME,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ScheduleHangup::MESSAGE_NOT_FOUND,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ScheduleHangup::MESSAGE_FAILED,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\ScheduleHangup::MESSAGE_SUCCESS
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

    /**
     * Unique identifier of the scheduled hangup request (UUIDv4)
     *
     * @OA\Property(
     *      example="21579000-0dca-4a75-bc1f-6eae8215a611"
     * )
     */
    public string $SchedHangupId;
}
