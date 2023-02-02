<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\FiCore\Command\Channel\Playback;

use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\FiCore\Switch\{
    Channel,
    Core
};

/**
 * @OA\Schema(
 *     schema="PlayStopParameters",
 *     required={"CallUUID"},
 * )
 */
class PlayStop extends AbstractInquiry
{
    /**
     * @OA\Property(
     *     description="Unique identifier of the call",
     *     example="441afb63-85bc-49d4-9ac8-8459f9bf5e6b",
     * )
     */
    public string $CallUUID;

    public Channel $channel;

    public function export(): Playback\Request
    {
        $request = new Playback\Request();

        $request->action = Playback\ActionEnum::Stop;
        $request->channel = $this->channel;

        return $request;
    }
}
