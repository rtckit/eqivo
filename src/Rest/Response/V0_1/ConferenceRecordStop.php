<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

use RTCKit\FiCore\Command\Conference\Record;

use RTCKit\FiCore\Command\ResponseInterface;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;

/**
 * @OA\Schema(
 *      schema="ConferenceRecordStopResponse",
 *      required={"Message", "RecordFile", "Success"},
 * )
 */
class ConferenceRecordStop extends AbstractResponse
{
    public const MESSAGE_SUCCESS = 'Conference RecordStop Executed';

    public const MESSAGE_NO_CONFERENCE_NAME = 'ConferenceName Parameter must be present';

    public const MESSAGE_NO_RECORD_FILE = 'RecordFile Parameter must be present';

    public const MESSAGE_FAILED = 'Conference RecordStop Failed';

    public const MESSAGE_NOT_FOUND = 'Conference RecordStop Failed -- Conference not found';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStop::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStop::MESSAGE_NO_CONFERENCE_NAME,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStop::MESSAGE_NO_RECORD_FILE,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStop::MESSAGE_FAILED,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStop::MESSAGE_NOT_FOUND,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStop::MESSAGE_SUCCESS
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
