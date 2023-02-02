<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\FiCore\Command\Channel\Record;
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\FiCore\Switch\{
    Channel,
    Core
};

/**
 * @OA\Schema(
 *     schema="RecordStopParameters",
 *     required={"CallUUID", "RecordFile"},
 * )
 */
class RecordStop extends AbstractInquiry
{
    /**
     * @OA\Property(
     *     description="Unique identifier of the call",
     *     example="eacfa857-4001-4379-b79a-c7ef6d963bcb",
     * )
     */
    public string $CallUUID;

    /**
     * @OA\Property(
     *     description="Full path to recording file, as returned by RecordStart; `all` shorthand is also available",
     *     example="/tmp/recording/sample.wav",
     * )
     */
    public string $RecordFile;

    public Channel $channel;

    public function export(): Record\Request
    {
        $request = new Record\Request();

        $request->action = Record\ActionEnum::Stop;
        $request->channel = $this->channel;
        $request->medium = $this->RecordFile;

        return $request;
    }
}
