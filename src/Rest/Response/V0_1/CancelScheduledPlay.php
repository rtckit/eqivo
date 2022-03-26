<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

/**
 * @OA\Schema(
 *      schema="CancelScheduledPlayResponse",
 *      required={"Message", "Success"},
 * )
 */
class CancelScheduledPlay
{
    public const MESSAGE_SUCCESS = 'Scheduled Play Cancelation Executed';

    public const MESSAGE_NO_SCHEDPLAYID = 'SchedPlayId Parameter must be present';

    public const MESSAGE_NOT_FOUND = 'Scheduled Play Cancelation Failed -- ID not found';

    public const MESSAGE_FAILED = 'Scheduled Play Cancelation Failed';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\CancelScheduledPlay::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\CancelScheduledPlay::MESSAGE_NO_SCHEDPLAYID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\CancelScheduledPlay::MESSAGE_NOT_FOUND,
     *          RTCKit\Eqivo\Rest\Response\V0_1\CancelScheduledPlay::MESSAGE_FAILED,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\CancelScheduledPlay::MESSAGE_SUCCESS
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
