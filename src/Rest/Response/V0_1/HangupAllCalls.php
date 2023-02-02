<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

use RTCKit\FiCore\Command\Channel\Hangup;

use RTCKit\FiCore\Command\ResponseInterface;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;

/**
 * @OA\Schema(
 *      schema="HangupAllCallsResponse",
 *      required={"Message", "Success"},
 * )
 */
class HangupAllCalls extends AbstractResponse
{
    public const MESSAGE_SUCCESS = 'All Calls Hungup';

    public const MESSAGE_FAILED = 'Hangup Call Failed';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\HangupAllCalls::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\HangupAllCalls::MESSAGE_FAILED,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\HangupAllCalls::MESSAGE_SUCCESS
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
        assert($response instanceof Hangup\Response);

        $this->Success = $response->successful;
        $this->Message = $response->successful ? self::MESSAGE_SUCCESS : self::MESSAGE_FAILED;

        return $this;
    }
}
