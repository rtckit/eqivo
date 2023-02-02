<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

use RTCKit\FiCore\Command\Conference\Speak;

use RTCKit\FiCore\Command\ResponseInterface;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;

/**
 * @OA\Schema(
 *      schema="ConferenceSpeakResponse",
 *      required={"Message", "RecordFile", "Success"},
 * )
 */
class ConferenceSpeak extends AbstractResponse
{
    public const MESSAGE_SUCCESS = 'Conference Speak Executed';

    public const MESSAGE_NO_CONFERENCE_NAME = 'ConferenceName Parameter must be present';

    public const MESSAGE_NO_TEXT = 'Text Parameter must be present';

    public const MESSAGE_NO_MEMBER_ID = 'MemberID Parameter must be present';

    public const MESSAGE_NOT_FOUND = 'Conference Speak Failed -- Conference not found';

    public const MESSAGE_FAILED = 'Conference Speak Failed';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceSpeak::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceSpeak::MESSAGE_NO_CONFERENCE_NAME,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceSpeak::MESSAGE_NO_TEXT,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceSpeak::MESSAGE_NO_MEMBER_ID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceSpeak::MESSAGE_NOT_FOUND,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceSpeak::MESSAGE_FAILED,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\ConferenceSpeak::MESSAGE_SUCCESS
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
        assert($response instanceof Speak\Response);

        $this->Success = $response->successful;
        $this->Message = $response->successful ? self::MESSAGE_SUCCESS : self::MESSAGE_FAILED;

        return $this;
    }
}
