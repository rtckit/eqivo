<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\FiCore\Command\Conference\Speak;

use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\FiCore\Switch\Core;

/**
 * @OA\Schema(
 *     schema="ConferenceSpeakParameters",
 *     required={"ConferenceName", "Text", "MemberID"},
 * )
 */
class ConferenceSpeak extends AbstractInquiry
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
     *     description="Text to be synthesized",
     *     example="The quick brown fox jumps over the lazy dog",
     * )
     */
    public string $Text;

    /**
     * @OA\Property(
     *     description="List of comma separated member IDs to be affected; `all` shorthand is available too.",
     *     example="13,42",
     * )
     */
    public string $MemberID;

    public Core $core;

    public function export(): Speak\Request
    {
        $conference = $this->core->getConferenceByRoom($this->ConferenceName);
        $request = new Speak\Request();
        $request->action = Speak\ActionEnum::Speak;
        $request->text = $this->Text;
        $request->member = $this->MemberID;

        if (isset($conference)) {
            $request->conference = $conference;
        }

        return $request;
    }
}
