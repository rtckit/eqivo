<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\Eqivo\Command\Channel\SoundTouch as SoundTouchCommand;
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;

use RTCKit\FiCore\Switch\{
    Channel,
    Core,
    DirectionEnum,
};

/**
 * @OA\Schema(
 *     schema="SoundTouchParameters",
 *     required={"CallUUID"},
 * )
 */
class SoundTouch extends AbstractInquiry
{
    /**
     * @OA\Property(
     *     description="Unique identifier of the call to send DTMF to",
     *     example="b7054b68-0620-455a-8ac7-f8f126853b9d",
     * )
     */
    public string $CallUUID;

    /**
     * @OA\Property(
     *     description="Media stream to be altered, incoming or outgoing",
     *     example="in",
     *     enum={"in", "out"},
     *     default=RTCKit\Eqivo\Rest\Controller\V0_1\SoundTouch::DEFAULT_AUDIO_DIRECTION,
     * )
     */
    public string $AudioDirection;

    /**
     * @OA\Property(
     *     description="Adjust the pitch in semitones",
     *     type="float",
     *     example=2,
     *     minimum=-14,
     *     maximum=14,
     * )
     */
    public string|float $PitchSemiTones;

    /**
     * @OA\Property(
     *     description="Adjust the pitch in octaves",
     *     type="float",
     *     example=0.5,
     *     minimum=-1,
     *     maximum=1,
     * )
     */
    public string|float $PitchOctaves;

    /**
     * @OA\Property(
     *     description="Adjust the pitch",
     *     type="float",
     *     example=4,
     *     minimum=0,
     *     exclusiveMinimum=true,
     *     default=1,
     * )
     */
    public string|float $Pitch;

    /**
     * @OA\Property(
     *     description="Adjust the rate",
     *     type="float",
     *     example=3,
     *     minimum=0,
     *     exclusiveMinimum=true,
     *     default=1,
     * )
     */
    public string|float $Rate;

    /**
     * @OA\Property(
     *     description="Adjust the tempo",
     *     type="float",
     *     example=2,
     *     minimum=0,
     *     exclusiveMinimum=true,
     *     default=1,
     * )
     */
    public string|float $Tempo;

    public Channel $channel;

    public function export(): SoundTouchCommand\Request
    {
        $request = new SoundTouchCommand\Request();

        $request->action = SoundTouchCommand\ActionEnum::Start;
        $request->channel = $this->channel;
        $request->direction = DirectionEnum::from($this->AudioDirection);

        if (isset($this->PitchSemiTones)) {
            $request->pitchSemiTones = (float)$this->PitchSemiTones;
        }

        if (isset($this->PitchOctaves)) {
            $request->pitchOctaves = (float)$this->PitchOctaves;
        }

        if (isset($this->Pitch)) {
            $request->pitch = (float)$this->Pitch;
        }

        if (isset($this->Rate)) {
            $request->rate = (float)$this->Rate;
        }

        if (isset($this->Tempo)) {
            $request->tempo = (float)$this->Tempo;
        }

        return $request;
    }
}
