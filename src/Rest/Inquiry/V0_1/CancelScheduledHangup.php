<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\FiCore\Command\Channel\Hangup;
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\FiCore\Switch\{
    Core,
    ScheduledHangup,
};

/**
 * @OA\Schema(
 *     schema="CancelScheduledHangupParameters",
 *     required={"SchedHangupId"},
 * )
 */
class CancelScheduledHangup extends AbstractInquiry
{
    /**
     * @OA\Property(
     *     description="Unique identifier returned when scheduled hangup was originally requested",
     *     example="ea428fbd-ac9b-498c-8bb2-a36ac49f10fd",
     * )
     */
    public string $SchedHangupId;

    public ScheduledHangup $hup;

    public function export(): Hangup\Request
    {
        $request = new Hangup\Request();

        $request->action = Hangup\ActionEnum::Cancel;
        $request->schedHangup = $this->hup;

        return $request;
    }
}
