<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

use RTCKit\FiCore\Command\ResponseInterface;
use RTCKit\Eqivo\Command\Channel\SoundTouch as SoundTouchCommand;

use RTCKit\Eqivo\Rest\Response\AbstractResponse;

/**
 * @OA\Schema(
 *      schema="SoundTouchStopResponse",
 *      required={"Message", "Success"},
 * )
 */
class SoundTouchStop extends AbstractResponse
{
    public const MESSAGE_SUCCESS = 'SoundTouchStop Executed';

    public const MESSAGE_NO_CALLUUID = 'CallUUID Parameter Missing';

    public const MESSAGE_NOT_FOUND = 'SoundTouchStop Failed -- Call not found';

    public const MESSAGE_FAILED = 'SoundTouchStop Failed';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouchStop::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouchStop::MESSAGE_NO_CALLUUID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouchStop::MESSAGE_NOT_FOUND,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouchStop::MESSAGE_FAILED,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\SoundTouchStop::MESSAGE_SUCCESS
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
        assert($response instanceof SoundTouchCommand\Response);

        $this->Success = $response->successful;
        $this->Message = $response->successful ? self::MESSAGE_SUCCESS : self::MESSAGE_FAILED;

        return $this;
    }
}
