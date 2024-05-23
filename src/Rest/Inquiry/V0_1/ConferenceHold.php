<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\FiCore\Command\Conference\Member;
use RTCKit\FiCore\Command\RequestInterface;
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;

use RTCKit\FiCore\Switch\Core;

/**
 * @OA\Schema(
 *     schema="ConferenceHoldParameters",
 *     required={"ConferenceName", "MemberID"},
 * )
 */
class ConferenceHold extends AbstractInquiry
{
    /**
     * @OA\Property(
     *     description="Name of the conference in question",
     *     example="Room402",
     * )
     */
    public string $ConferenceName;

    /**
     * @OA\Property(
     *     description="Path/URI of the music on hold file to be played",
     *     example="/var/local/media/sample.wav",
     * )
     */
    public string $FilePath;

    /**
     * @OA\Property(
     *     description="List of comma separated member IDs to be affected; `all` shorthand is available too.",
     *     example="13,42",
     * )
     */
    public string $MemberID;

    public Core $core;

    public function export(): RequestInterface
    {
        $conference = $this->core->getConferenceByRoom($this->ConferenceName);
        $request = new Member\Request();
        $request->action = Member\ActionEnum::ExtHold;
        $request->members = explode(',', $this->MemberID);

        if (isset($this->FilePath)) {
            $request->medium = $this->FilePath;
        }

        if (isset($conference)) {
            $request->conference = $conference;
        }

        return $request;
    }
}
