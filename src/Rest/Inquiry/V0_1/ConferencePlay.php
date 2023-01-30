<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\FiCore\Command\Conference\Playback;

use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\FiCore\Switch\Core;

/**
 * @OA\Schema(
 *     schema="ConferencePlayParameters",
 *     required={"ConferenceName", "FilePath", "MemberID"},
 * )
 */
class ConferencePlay extends AbstractInquiry
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
     *     description="Path/URI of the media file to be played",
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

    public function export(): Playback\Request
    {
        $conference = $this->core->getConferenceByRoom($this->ConferenceName);
        $request = new Playback\Request();
        $request->action = Playback\ActionEnum::Play;
        $request->medium = $this->FilePath;
        $request->member = $this->MemberID;

        if (isset($conference)) {
            $request->conference = $conference;
        }

        return $request;
    }
}
