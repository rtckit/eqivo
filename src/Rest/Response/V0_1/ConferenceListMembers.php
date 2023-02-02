<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

use RTCKit\FiCore\Command\ResponseInterface;
use RTCKit\Eqivo\Command\Conference\Query;

use RTCKit\Eqivo\Rest\Response\AbstractResponse;

/**
 * @OA\Schema(
 *      schema="ConferenceListMembersResponse",
 *      required={"Message", "Success", "List"},
 * )
 */
class ConferenceListMembers extends AbstractResponse
{
    public const MESSAGE_SUCCESS = 'Conference ListMembers Executed';

    public const MESSAGE_FAILED = 'Conference List Failed';

    public const MESSAGE_NO_CONFERENCE_NAME = 'ConferenceName Parameter must be present';

    public const MESSAGE_PARSE_ERROR = 'Conference ListMembers Failed to parse result';

    public const MESSAGE_NOT_FOUND = 'Conference ListMembers Failed -- Conference not found';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceListMembers::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceListMembers::MESSAGE_NO_CONFERENCE_NAME,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceListMembers::MESSAGE_PARSE_ERROR,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceListMembers::MESSAGE_NOT_FOUND,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\ConferenceListMembers::MESSAGE_SUCCESS
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

    /**
     * List of established conferences
     *
     * @var array<string, mixed>
     * @OA\Property(
     *      type="object",
     *      example={
     *          "Room402": {
     *              "ConferenceMemberCount": "1",
     *              "ConferenceName": "Room402",
     *              "ConferenceRunTime": "79",
     *              "ConferenceUUID": "5105acbf-6d43-4d67-8536-19999924eba4",
     *              "Members": {
     *                  {
     *                      "Muted": false,
     *                      "Deaf": false,
     *                      "MemberID": "14",
     *                      "CallNumber": "3985",
     *                      "CallName": "DeskPhone985",
     *                      "CallUUID": "8f72b48e-d97e-425a-a6e6-ae5a6a0dc231",
     *                      "JoinTime": "79"
     *                  }
     *              }
     *          }
     *      }
     * )
     */
    public array $List = [];

    public function import(ResponseInterface $response): static
    {
        assert($response instanceof Query\Response);

        $this->Success = true;
        $this->Message = $response->successful ? self::MESSAGE_SUCCESS : self::MESSAGE_FAILED;
        $this->List = $response->rooms;

        return $this;
    }
}
