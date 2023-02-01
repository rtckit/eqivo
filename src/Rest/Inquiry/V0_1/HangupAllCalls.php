<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\FiCore\Command\Channel\Hangup;
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;

use RTCKit\FiCore\Switch\HangupCauseEnum;

/**
 * @OA\Schema(
 *     schema="HangupAllCallsParameters",
 * )
 */
class HangupAllCalls extends AbstractInquiry
{
    public function export(): Hangup\Request
    {
        $request = new Hangup\Request();

        $request->action = Hangup\ActionEnum::All;
        $request->cause = HangupCauseEnum::NORMAL_CLEARING;

        return $request;
    }
}
