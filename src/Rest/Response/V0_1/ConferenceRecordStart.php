<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

use RTCKit\FiCore\Command\Conference\Record;

use RTCKit\FiCore\Command\ResponseInterface;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;

/**
 * @OA\Schema(
 *      schema="ConferenceRecordStartResponse",
 *      required={"Message", "RecordFile", "Success"},
 * )
 */
class ConferenceRecordStart extends AbstractResponse
{
    public const MESSAGE_SUCCESS = 'Conference RecordStart Executed';

    public const MESSAGE_NO_CONFERENCE_NAME = 'ConferenceName Parameter must be present';

    public const MESSAGE_BAD_FILE_FORMAT = 'FileFormat Parameter must be';

    public const MESSAGE_FAILED = 'Conference RecordStart Failed';

    public const MESSAGE_NOT_FOUND = 'Conference RecordStart Failed -- Conference not found';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStart::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStart::MESSAGE_NO_CONFERENCE_NAME,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStart::MESSAGE_BAD_FILE_FORMAT,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStart::MESSAGE_FAILED,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStart::MESSAGE_NOT_FOUND,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStart::MESSAGE_SUCCESS
     * )
     */
    public string $Message;

    /**
     * Directory path/URI where the recording file will be saved
     *
     * @OA\Property(
     *     example="/tmp/recordings/sample.mp3",
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
