<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\Eqivo\Command\Channel\SoundTouch as SoundTouchCommand;
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;

use RTCKit\FiCore\Switch\{
    Channel,
    Core,
};

/**
 * @OA\Schema(
 *     schema="SoundTouchStopParameters",
 *     required={"CallUUID"},
 * )
 */
class SoundTouchStop extends AbstractInquiry
{
    /**
     * @OA\Property(
     *     description="Unique identifier of the call",
     *     example="fe372011-face-4bc2-bbcc-893d045bf67d",
     * )
     */
    public string $CallUUID;

    public Channel $channel;

    public function export(): SoundTouchCommand\Request
    {
        $request = new SoundTouchCommand\Request();

        $request->action = SoundTouchCommand\ActionEnum::Stop;
        $request->channel = $this->channel;

        return $request;
    }
}
