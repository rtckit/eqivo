<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

/**
 * @OA\Schema(
 *      schema="TransferCallResponse",
 *      required={"Message", "Success"},
 * )
 */
class TransferCall
{
    public const MESSAGE_SUCCESS = 'Transfer Call Executed';

    public const MESSAGE_NO_CALLUUID = 'CallUUID Parameter must be present';

    public const MESSAGE_NO_URL = 'Url Parameter must be present';

    public const MESSAGE_INVALID_URL = 'Url is not Valid';

    public const MESSAGE_NOT_FOUND = 'Transfer Call Failed -- Call not found';

    public const MESSAGE_FAILED = 'Transfer Call Failed';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\TransferCall::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\TransferCall::MESSAGE_NO_CALLUUID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\TransferCall::MESSAGE_NO_URL,
     *          RTCKit\Eqivo\Rest\Response\V0_1\TransferCall::MESSAGE_INVALID_URL,
     *          RTCKit\Eqivo\Rest\Response\V0_1\TransferCall::MESSAGE_NOT_FOUND,
     *          RTCKit\Eqivo\Rest\Response\V0_1\TransferCall::MESSAGE_FAILED,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\TransferCall::MESSAGE_SUCCESS
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
