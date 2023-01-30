<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\FiCore\Command\Channel\Hangup;
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\FiCore\Switch\{
    Channel,
    Core,
    HangupCauseEnum,
};

/**
 * @OA\Schema(
 *     schema="ScheduleHangupParameters",
 *     required={"CallUUID", "Time"},
 * )
 */
class ScheduleHangup extends AbstractInquiry
{
    /**
     * @OA\Property(
     *     description="Unique identifier of the call",
     *     example="f84fbadc-5df0-4c02-934b-aac0c1efb8ae",
     * )
     */
    public string $CallUUID;

    /**
     * @OA\Property(
     *     description="Time (in seconds) after which the call in question will be hung up",
     *     type="int",
     *     minimum=1,
     *     example=59,
     * )
     */
    public string|int $Time;

    public Channel $channel;

    public function export(): Hangup\Request
    {
        $request = new Hangup\Request();

        $request->action = Hangup\ActionEnum::Schedule;
        $request->cause = HangupCauseEnum::ALLOTTED_TIMEOUT;
        $request->channel = $this->channel;
        $request->delay = (int)$this->Time;

        return $request;
    }
}
