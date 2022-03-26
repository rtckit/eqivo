<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

/**
 * @OA\Schema(
 *      schema="ConferenceMuteResponse",
 *      required={"Message", "Success"},
 * )
 */
class ConferenceMute
{
    public const MESSAGE_SUCCESS = 'Conference Mute Executed';

    public const MESSAGE_NO_CONFERENCE_NAME = 'ConferenceName Parameter must be present';

    public const MESSAGE_NO_MEMBER_ID = 'MemberID Parameter must be present';

    public const MESSAGE_NOT_FOUND = 'Conference Mute Failed -- Conference not found';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceMute::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceMute::MESSAGE_NO_CONFERENCE_NAME,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceMute::MESSAGE_NO_MEMBER_ID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\ConferenceMute::MESSAGE_NOT_FOUND,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\ConferenceMute::MESSAGE_SUCCESS
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
}
