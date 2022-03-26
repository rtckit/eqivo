<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

/**
 * @OA\Schema(
 *      schema="HangupCallResponse",
 *      required={"Message", "Success"},
 * )
 */
class HangupCall
{
    public const MESSAGE_SUCCESS = 'Hangup Call Executed';

    public const MESSAGE_NO_PARAMETERS = 'CallUUID or RequestUUID Parameter must be present';

    public const MESSAGE_BOTH_PRESENT = 'Both CallUUID and RequestUUID Parameters cannot be present';

    public const MESSAGE_FAILED = 'Hangup Call Failed';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\HangupCall::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\HangupCall::MESSAGE_NO_PARAMETERS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\HangupCall::MESSAGE_BOTH_PRESENT,
     *          RTCKit\Eqivo\Rest\Response\V0_1\HangupCall::MESSAGE_FAILED,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\HangupCall::MESSAGE_SUCCESS
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
