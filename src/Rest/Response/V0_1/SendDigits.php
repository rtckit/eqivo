<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

use RTCKit\FiCore\Command\Channel\DTMF;

use RTCKit\FiCore\Command\ResponseInterface;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;

/**
 * @OA\Schema(
 *      schema="SendDigitsResponse",
 *      required={"Message", "Success", "SchedPlayId"},
 * )
 */
class SendDigits extends AbstractResponse
{
    public const MESSAGE_SUCCESS = 'SendDigits Executed';

    public const MESSAGE_NO_CALLUUID = 'CallUUID Parameter Missing';

    public const MESSAGE_NO_DIGITS = 'Digits Parameter Missing';

    public const MESSAGE_INVALID_LEG = 'Invalid Leg Parameter';

    public const MESSAGE_NOT_FOUND = 'SendDigits Failed -- Call not found';

    public const MESSAGE_FAILED = 'SendDigits Failed';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\SendDigits::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SendDigits::MESSAGE_NO_CALLUUID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SendDigits::MESSAGE_NO_DIGITS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SendDigits::MESSAGE_INVALID_LEG,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SendDigits::MESSAGE_NOT_FOUND,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SendDigits::MESSAGE_FAILED,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\SendDigits::MESSAGE_SUCCESS
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

    public function import(ResponseInterface $response): static
    {
        assert($response instanceof DTMF\Response);

        $this->Success = $response->successful;
        $this->Message = $response->successful ? self::MESSAGE_SUCCESS : self::MESSAGE_FAILED;

        return $this;
    }
}
