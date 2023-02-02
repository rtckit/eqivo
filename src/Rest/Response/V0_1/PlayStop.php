<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

use RTCKit\FiCore\Command\Channel\Playback;

use RTCKit\FiCore\Command\ResponseInterface;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;

/**
 * @OA\Schema(
 *      schema="PlayStopResponse",
 *      required={"Message", "Success"},
 * )
 */
class PlayStop extends AbstractResponse
{
    public const MESSAGE_SUCCESS = 'PlayStop Executed';

    public const MESSAGE_NO_CALLUUID = 'CallUUID Parameter Missing';

    public const MESSAGE_NOT_FOUND = 'PlayStop Failed -- Call not found';

    public const MESSAGE_FAILED = 'PlayStop Failed';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\PlayStop::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\PlayStop::MESSAGE_NO_CALLUUID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\PlayStop::MESSAGE_NOT_FOUND,
     *          RTCKit\Eqivo\Rest\Response\V0_1\PlayStop::MESSAGE_FAILED,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\PlayStop::MESSAGE_SUCCESS
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
        assert($response instanceof Playback\Response);

        $this->Success = $response->successful;
        $this->Message = $response->successful ? self::MESSAGE_SUCCESS : self::MESSAGE_FAILED;

        return $this;
    }
}
