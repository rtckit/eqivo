<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\FiCore\Command\Channel\Playback;
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;

use RTCKit\FiCore\Switch\{
    Core,
    ScheduledPlay
};

/**
 * @OA\Schema(
 *     schema="CancelScheduledPlayParameters",
 *     required={"SchedPlayId"},
 * )
 */
class CancelScheduledPlay extends AbstractInquiry
{
    /**
     * @OA\Property(
     *     description="Unique identifier returned when scheduled playback was originally requested",
     *     example="ea428fbd-ac9b-498c-8bb2-a36ac49f10fd",
     * )
     */
    public string $SchedPlayId;

    public ScheduledPlay $play;

    public function export(): Playback\Request
    {
        $request = new Playback\Request();

        $request->action = Playback\ActionEnum::Cancel;
        $request->schedPlay = $this->play;

        return $request;
    }
}
