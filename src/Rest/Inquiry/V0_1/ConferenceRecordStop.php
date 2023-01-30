<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\FiCore\Command\Conference\Record;

use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\FiCore\Switch\Core;

/**
 * @OA\Schema(
 *     schema="ConferenceRecordStopParameters",
 *     required={"ConferenceName", "RecordFile"},
 * )
 */
class ConferenceRecordStop extends AbstractInquiry
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
     *     description="Full path to recording file, as returned by ConferenceRecordStart; `all` shorthand is also available",
     *     example="/tmp/recording/sample.wav",
     * )
     */
    public string $RecordFile;

    public Core $core;

    public function export(): Record\Request
    {
        $conference = $this->core->getConferenceByRoom($this->ConferenceName);
        $request = new Record\Request();
        $request->action = Record\ActionEnum::Stop;
        $request->medium = $this->RecordFile;

        if (isset($conference)) {
            $request->conference = $conference;
        }

        return $request;
    }
}
