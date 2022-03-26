<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

/**
 * @OA\Schema(
 *      schema="PlayResponse",
 *      required={"Message", "Success"},
 * )
 */
class Play
{
    public const MESSAGE_SUCCESS = 'Play Executed';

    public const MESSAGE_NO_CALLUUID = 'CallUUID Parameter Missing';

    public const MESSAGE_NO_SOUNDS = 'Sounds Parameter Missing';

    public const MESSAGE_INVALID_LEG = 'Legs Parameter is Invalid';

    public const MESSAGE_INVALID_LENGTH = 'Length Parameter must be a positive integer';

    public const MESSAGE_INVALID_SOUNDS = 'Sounds Parameter is Invalid';

    public const MESSAGE_NOT_FOUND = 'Play Failed -- Call not found';

    public const MESSAGE_FAILED = 'Play Failed';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\Play::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\Play::MESSAGE_NO_CALLUUID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\Play::MESSAGE_NO_SOUNDS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\Play::MESSAGE_INVALID_LEG,
     *          RTCKit\Eqivo\Rest\Response\V0_1\Play::MESSAGE_INVALID_LENGTH,
     *          RTCKit\Eqivo\Rest\Response\V0_1\Play::MESSAGE_INVALID_SOUNDS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\Play::MESSAGE_NOT_FOUND,
     *          RTCKit\Eqivo\Rest\Response\V0_1\Play::MESSAGE_FAILED,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\Play::MESSAGE_SUCCESS
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
