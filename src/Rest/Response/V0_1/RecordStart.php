<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

use RTCKit\FiCore\Command\Channel\Record;

use RTCKit\FiCore\Command\ResponseInterface;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;

/**
 * @OA\Schema(
 *      schema="RecordStartResponse",
 *      required={"Message", "RecordFile", "Success"},
 * )
 */
class RecordStart extends AbstractResponse
{
    public const MESSAGE_SUCCESS = 'RecordStart Executed';

    public const MESSAGE_NO_CALLUUID = 'CallUUID Parameter must be present';

    public const MESSAGE_BAD_FILE_FORMAT = 'FileFormat Parameter must be';

    public const MESSAGE_INVALID_TIME_LIMIT = 'RecordStart Failed: invalid TimeLimit';

    public const MESSAGE_NOT_FOUND = 'RecordStart Failed -- Call not found';

    public const MESSAGE_FAILED = 'RecordStart Failed';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\RecordStart::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\RecordStart::MESSAGE_NO_CALLUUID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\RecordStart::MESSAGE_BAD_FILE_FORMAT,
     *          RTCKit\Eqivo\Rest\Response\V0_1\RecordStart::MESSAGE_INVALID_TIME_LIMIT,
     *          RTCKit\Eqivo\Rest\Response\V0_1\RecordStart::MESSAGE_NOT_FOUND,
     *          RTCKit\Eqivo\Rest\Response\V0_1\RecordStart::MESSAGE_FAILED,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\RecordStart::MESSAGE_SUCCESS
     * )
     */
    public string $Message;

    /**
     * Directory path/URI where the recording file will be saved
     *
     * @OA\Property(
     *     example="/tmp/recordings/sample.wav",
     * )
     */
    public string $RecordFile;

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

        if (isset($response->medium)) {
            $this->RecordFile = $response->medium;
        }

        return $this;
    }
}
