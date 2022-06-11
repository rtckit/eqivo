<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

/**
 * @OA\Schema(
 *      schema="CallResponse",
 *      required={"Message", "RequestUUID", "Success", "RestApiServer"},
 * )
 */
class Call
{
    public const MESSAGE_SUCCESS = 'Call Request Executed';

    public const MESSAGE_MANDATORY_MISSING = 'Mandatory Parameters Missing';

    public const MESSAGE_ANSWERURL_INVALID = 'AnswerUrl is not Valid';

    public const MESSAGE_HANGUPURL_INVALID = 'HangupUrl is not Valid';

    public const MESSAGE_RINGURL_INVALID = 'RingUrl is not Valid';

    public const MESSAGE_UNKNOWN_CORE = 'Unknown Core UUID';

    public const MESSAGE_INVALID_AMD = 'MachineDetection is not Valid';

    public const MESSAGE_INVALID_AMD_METHOD = "AsyncAmdStatusCallbackMethod must be 'GET' or 'POST'";

    public const MESSAGE_AMD_URL_INVALID = 'AsyncAmdStatusCallback is not Valid';

    public const MESSAGE_AMD_TIMEOUT_NOT_INT = 'MachineDetectionTimeout must be an integer';

    public const MESSAGE_AMD_TIMEOUT_BAD_RANGE = 'MachineDetectionTimeout Parameter must be between 3 and 59';

    public const MESSAGE_AMD_SPEECH_THRESHOLD_NOT_INT = 'MachineDetectionSpeechThreshold must be an integer';

    public const MESSAGE_AMD_SPEECH_THRESHOLD_BAD_RANGE = 'MachineDetectionSpeechThreshold Parameter must be between 1000 and 6000';

    public const MESSAGE_AMD_SILENCE_THRESHOLD_NOT_INT = 'MachineDetectionSpeechEndThreshold must be an integer';

    public const MESSAGE_AMD_SILENCE_THRESHOLD_BAD_RANGE = 'MachineDetectionSpeechEndThreshold Parameter must be between 500 and 5000';

    public const MESSAGE_AMD_INITIAL_SILENCE_NOT_INT = 'MachineDetectionSilenceTimeout must be an integer';

    public const MESSAGE_AMD_INITIAL_SILENCE_BAD_RANGE = 'MachineDetectionSilenceTimeout Parameter must be between 2000 and 10000';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\Call::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\Call::MESSAGE_MANDATORY_MISSING,
     *          RTCKit\Eqivo\Rest\Response\V0_1\Call::MESSAGE_ANSWERURL_INVALID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\Call::MESSAGE_HANGUPURL_INVALID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\Call::MESSAGE_RINGURL_INVALID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\Call::MESSAGE_UNKNOWN_CORE,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\Call::MESSAGE_SUCCESS
     * )
     */
    public string $Message;

    /**
     * Unique identifier of the Call request (UUIDv4)
     *
     * @OA\Property(
     *      example="fc92f3f4-3777-43ed-b269-5b4a0d474b12"
     * )
     */
    public string $RequestUUID;

    /**
     * Whether the request was successful or not
     *
     * @OA\Property(
     *      example=true
     * )
     */
    public bool $Success;

    /**
     * API server which handled this request (an Eqivo extension)
     *
     * @OA\Property(
     *      example="localhost"
     * )
     */
    public string $RestApiServer;
}
