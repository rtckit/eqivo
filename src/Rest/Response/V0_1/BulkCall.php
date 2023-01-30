<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

use RTCKit\FiCore\Command\Channel\Originate;

use RTCKit\FiCore\Command\ResponseInterface;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;

/**
 * @OA\Schema(
 *      schema="BulkCallResponse",
 *      required={"Message", "RequestUUID", "Success", "RestApiServer"},
 * )
 */
class BulkCall extends AbstractResponse
{
    public const MESSAGE_SUCCESS = 'BulkCalls Request Executed';

    public const MESSAGE_FAILED = 'BulkCalls Request Failed';

    public const MESSAGE_MANDATORY_MISSING = 'Mandatory Parameters Missing';

    public const MESSAGE_DELIMITER_DISALLOWED = 'This Delimiter is not allowed';

    public const MESSAGE_INSUFFICIENT_NUMBERS = 'BulkCalls should be used for at least 2 numbers';

    public const MESSAGE_MISMATCHED_PARAMETERS = "'To' parameter length does not match 'Gateways' Length";

    public const MESSAGE_ANSWERURL_INVALID = 'AnswerUrl is not Valid';

    public const MESSAGE_HANGUPURL_INVALID = 'HangupUrl is not Valid';

    public const MESSAGE_RINGURL_INVALID = 'RingUrl is not Valid';

    public const MESSAGE_UNKNOWN_CORE = 'Unknown Core UUID';

    /**
     * Response message
     *
     * @var string
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\BulkCall::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\BulkCall::MESSAGE_MANDATORY_MISSING,
     *          RTCKit\Eqivo\Rest\Response\V0_1\BulkCall::MESSAGE_DELIMITER_DISALLOWED,
     *          RTCKit\Eqivo\Rest\Response\V0_1\BulkCall::MESSAGE_INSUFFICIENT_NUMBERS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\BulkCall::MESSAGE_MISMATCHED_PARAMETERS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\BulkCall::MESSAGE_ANSWERURL_INVALID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\BulkCall::MESSAGE_HANGUPURL_INVALID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\BulkCall::MESSAGE_RINGURL_INVALID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\BulkCall::MESSAGE_UNKNOWN_CORE,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\BulkCall::MESSAGE_SUCCESS
     * )
     */
    public string $Message;

    /**
     * Unique identifiers of each Call request (UUIDv4)
     *
     * @var list<string>
     * @OA\Property(
     *      @OA\Items(type="string"),
     *      example={"fc92f3f4-3777-43ed-b269-5b4a0d474b12", "22f94a34-2890-4f18-ab99-5c47e25dd3c3", "d1337342-0225-465d-b7bb-1b5d88eb39d2"}
     * )
     */
    public array $RequestUUID = [];

    /**
     * Whether the request was successful or not
     *
     * @OA\Property(
     *      example=true
     * )
     */
    public bool $Success;

    /**
     * API server which handled this request (an FiCore extension)
     *
     * @OA\Property(
     *      example="localhost"
     * )
     */
    public string $RestApiServer;

    public function import(ResponseInterface $response): static
    {
        assert($response instanceof Originate\Response);

        $this->Success = $response->successful;
        $this->Message = $response->successful ? self::MESSAGE_SUCCESS : self::MESSAGE_FAILED;

        foreach($response->originateJobs as $job) {
            $this->RequestUUID[] = $job->uuid;
        }

        return $this;
    }
}
