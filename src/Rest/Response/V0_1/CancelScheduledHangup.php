<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

/**
 * @OA\Schema(
 *      schema="CancelScheduledHangupResponse",
 *      required={"Message", "Success"},
 * )
 */
class CancelScheduledHangup
{
    public const MESSAGE_SUCCESS = 'Scheduled Hangup Cancelation Executed';

    public const MESSAGE_NO_SCHEDHUPID = 'SchedHangupId Parameter must be present';

    public const MESSAGE_NOT_FOUND = 'Scheduled Hangup Cancelation Failed -- ID not found';

    public const MESSAGE_FAILED = 'Scheduled Hangup Cancelation Failed';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\CancelScheduledHangup::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\CancelScheduledHangup::MESSAGE_NO_SCHEDHUPID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\CancelScheduledHangup::MESSAGE_NOT_FOUND,
     *          RTCKit\Eqivo\Rest\Response\V0_1\CancelScheduledHangup::MESSAGE_FAILED,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\CancelScheduledHangup::MESSAGE_SUCCESS
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
