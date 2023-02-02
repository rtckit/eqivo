<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\FiCore\Command\Channel\Hangup;
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;

use RTCKit\FiCore\Switch\{
    Channel,
    HangupCauseEnum,
    OriginateJob,
};

/**
 * @OA\Schema(
 *     schema="HangupCallParameters",
 * )
 */
class HangupCall extends AbstractInquiry
{
    /**
     * @OA\Property(
     *     description="Unique identifier of the call (when established); this parameter is mutually exclusive with RequestUUID",
     *     example="b0519011-6987-47c8-9270-a820e0978acd",
     * )
     */
    public string $CallUUID;

    /**
     * @OA\Property(
     *     description="Unique identifier of the API request (when the call is not established yet); this parameter is mutually exclusive with CallUUID",
     *     example="c059b96b-04d8-414b-920c-7b373bff916e",
     * )
     */
    public string $RequestUUID;

    public Channel $channel;

    public OriginateJob $job;

    public function export(): Hangup\Request
    {
        $request = new Hangup\Request();

        if (isset($this->channel)) {
            $request->action = Hangup\ActionEnum::Channel;
            $request->cause = HangupCauseEnum::NORMAL_CLEARING;
            $request->channel = $this->channel;
        } else {
            $request->action = Hangup\ActionEnum::Job;
            $request->cause = HangupCauseEnum::NORMAL_CLEARING;
            $request->originateJob = $this->job;
        }

        return $request;
    }
}
