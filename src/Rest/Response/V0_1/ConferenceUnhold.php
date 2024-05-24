<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

use RTCKit\FiCore\Command\Conference\Member;

use RTCKit\FiCore\Command\ResponseInterface;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;

/**
 * @OA\Schema(
 *      schema="ConferenceUnholdResponse",
 *      required={"Message", "Success"},
 * )
 */
class ConferenceUnhold extends AbstractResponse
{
    public const MESSAGE_SUCCESS = 'Conference Unhold Executed';

    public const MESSAGE_FAILED = 'Conference Unhold Failed';

    public const MESSAGE_NO_CONFERENCE_NAME = 'ConferenceName Parameter must be present';

    public const MESSAGE_NO_MEMBER_ID = 'MemberID Parameter must be present';

    public const MESSAGE_NOT_FOUND = 'Conference Unhold Failed -- Conference not found';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceUnhold::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceUnhold::MESSAGE_NO_CONFERENCE_NAME,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceUnhold::MESSAGE_NO_MEMBER_ID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceUnhold::MESSAGE_NOT_FOUND,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\ConferenceUnhold::MESSAGE_SUCCESS
     * )
     */
    public string $Message;

    /**
     * List of affected members
     *
     * @var list<string>
     * @OA\Property(
     *      @OA\Items(type="string"),
     *      example={"13", "42"}
     * )
     */
    public array $Members = [];

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
        assert($response instanceof Member\Response);

        $this->Success = true;
        $this->Message = $response->successful ? self::MESSAGE_SUCCESS : self::MESSAGE_FAILED;
        $this->Members = $response->members;

        return $this;
    }
}
