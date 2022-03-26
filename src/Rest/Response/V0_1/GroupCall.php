<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

/**
 * @OA\Schema(
 *      schema="GroupCallResponse",
 *      required={"Message", "RequestUUID", "Success", "RestApiServer"},
 * )
 */
class GroupCall
{
    public const MESSAGE_SUCCESS = 'GroupCall Request Executed';

    public const MESSAGE_MANDATORY_MISSING = 'Mandatory Parameters Missing';

    public const MESSAGE_DELIMITER_DISALLOWED = 'This Delimiter is not allowed';

    public const MESSAGE_INSUFFICIENT_NUMBERS = 'GroupCall should be used for at least 2 numbers';

    public const MESSAGE_MISMATCHED_PARAMETERS = "'To' parameter length does not match 'Gateways' Length";

    public const MESSAGE_ANSWERURL_INVALID = 'AnswerUrl is not Valid';

    public const MESSAGE_HANGUPURL_INVALID = 'HangupUrl is not Valid';

    public const MESSAGE_RINGURL_INVALID = 'RingUrl is not Valid';

    public const MESSAGE_CONFIRMSOUND_INVALID = 'ConfirmSound is not Valid';

    public const MESSAGE_UNKNOWN_CORE = 'Unknown Core UUID';

    public const MESSAGE_FAILED = 'GroupCall Request Failed';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\GroupCall::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\GroupCall::MESSAGE_MANDATORY_MISSING,
     *          RTCKit\Eqivo\Rest\Response\V0_1\GroupCall::MESSAGE_DELIMITER_DISALLOWED,
     *          RTCKit\Eqivo\Rest\Response\V0_1\GroupCall::MESSAGE_INSUFFICIENT_NUMBERS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\GroupCall::MESSAGE_MISMATCHED_PARAMETERS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\GroupCall::MESSAGE_ANSWERURL_INVALID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\GroupCall::MESSAGE_HANGUPURL_INVALID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\GroupCall::MESSAGE_RINGURL_INVALID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\GroupCall::MESSAGE_CONFIRMSOUND_INVALID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\GroupCall::MESSAGE_UNKNOWN_CORE,
     *          RTCKit\Eqivo\Rest\Response\V0_1\GroupCall::MESSAGE_FAILED,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\GroupCall::MESSAGE_SUCCESS
     * )
     */
    public string $Message;

    /**
     * Unique identifier of the Call request (UUIDv4)
     *
     * @OA\Property(
     *      example="fc01f3f4-4895-43ed-b269-5b4a0d474b12"
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
