<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

use RTCKit\FiCore\Command\Channel\Record;

use RTCKit\FiCore\Command\ResponseInterface;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;

/**
 * @OA\Schema(
 *      schema="RecordStopResponse",
 *      required={"Message", "Success"},
 * )
 */
class RecordStop extends AbstractResponse
{
    public const MESSAGE_SUCCESS = 'RecordStop Executed';

    public const MESSAGE_NO_CALLUUID = 'CallUUID Parameter must be present';

    public const MESSAGE_NO_RECORD_FILE = 'RecordFile Parameter must be present';

    public const MESSAGE_NOT_FOUND = 'RecordStop Failed -- Call not found';

    public const MESSAGE_FAILED = 'RecordStop Failed';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\RecordStop::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\RecordStop::MESSAGE_NO_CALLUUID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\RecordStop::MESSAGE_NO_RECORD_FILE,
     *          RTCKit\Eqivo\Rest\Response\V0_1\RecordStop::MESSAGE_NOT_FOUND,
     *          RTCKit\Eqivo\Rest\Response\V0_1\RecordStop::MESSAGE_FAILED,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\RecordStop::MESSAGE_SUCCESS
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
        assert($response instanceof Record\Response);

        $this->Success = $response->successful;
        $this->Message = $response->successful ? self::MESSAGE_SUCCESS : self::MESSAGE_FAILED;

        return $this;
    }
}
