<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Response\V0_1;

/**
 * @OA\Schema(
 *      schema="SoundTouchResponse",
 *      required={"Message", "Success"},
 * )
 */
class SoundTouch
{
    public const MESSAGE_SUCCESS = 'SoundTouch Executed';

    public const MESSAGE_NO_CALLUUID = 'CallUUID Parameter Missing';

    public const MESSAGE_INVALID_AUDIO_DIRECTION = "AudioDirection Parameter Must be 'in' or 'out'";

    public const MESSAGE_PITCHSEMITONES_NOT_FLOAT = 'PitchSemiTones Parameter must be float';

    public const MESSAGE_PITCHSEMITONES_BAD_RANGE = 'PitchSemiTones Parameter must be between -14 and 14';

    public const MESSAGE_PITCHOCTAVES_NOT_FLOAT = 'PitchOctaves Parameter must be float';

    public const MESSAGE_PITCHOCTAVES_BAD_RANGE = 'PitchOctaves Parameter must be between -1 and 1';

    public const MESSAGE_PITCH_NOT_FLOAT = 'Pitch Parameter must be float';

    public const MESSAGE_PITCH_BAD_RANGE = 'Pitch Parameter must be > 0';

    public const MESSAGE_RATE_NOT_FLOAT = 'Rate Parameter must be float';

    public const MESSAGE_RATE_BAD_RANGE = 'Rate Parameter must be > 0';

    public const MESSAGE_TEMPO_NOT_FLOAT = 'Tempo Parameter must be float';

    public const MESSAGE_TEMPO_BAD_RANGE = 'Tempo Parameter must be > 0';

    public const MESSAGE_NOT_FOUND = 'SoundTouch Failed -- Call not found';

    public const MESSAGE_FAILED = 'SoundTouch Failed';

    /**
     * Response message
     *
     * @OA\Property(
     *      enum={
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouch::MESSAGE_SUCCESS,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouch::MESSAGE_NO_CALLUUID,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouch::MESSAGE_INVALID_AUDIO_DIRECTION,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouch::MESSAGE_PITCHSEMITONES_NOT_FLOAT,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouch::MESSAGE_PITCHSEMITONES_BAD_RANGE,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouch::MESSAGE_PITCHOCTAVES_NOT_FLOAT,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouch::MESSAGE_PITCHOCTAVES_BAD_RANGE,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouch::MESSAGE_PITCH_NOT_FLOAT,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouch::MESSAGE_PITCH_BAD_RANGE,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouch::MESSAGE_RATE_NOT_FLOAT,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouch::MESSAGE_RATE_BAD_RANGE,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouch::MESSAGE_TEMPO_NOT_FLOAT,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouch::MESSAGE_TEMPO_BAD_RANGE,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouch::MESSAGE_NOT_FOUND,
     *          RTCKit\Eqivo\Rest\Response\V0_1\SoundTouch::MESSAGE_FAILED,
     *      },
     *      example=RTCKit\Eqivo\Rest\Response\V0_1\SoundTouch::MESSAGE_SUCCESS
     * )
     */
    public string $Message;

    /**
     * Whether the request was successful or not
     *
     * @OA\Property(
     *      example=true
     * )
     */
    public bool $Success;
}
