<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use function React\Promise\{
    all,
    resolve
};
use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
};
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\Eqivo\Rest\Inquiry\V0_1\SoundTouch as SoundTouchInquiry;

use RTCKit\Eqivo\Rest\Response\AbstractResponse;
use RTCKit\Eqivo\Rest\Response\V0_1\SoundTouch as SoundTouchResponse;
use RTCKit\Eqivo\Rest\View\V0_1\SoundTouch as SoundTouchView;

use RTCKit\FiCore\Switch\DirectionEnum;

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/SoundTouch/",
 *      summary="/v0.1/SoundTouch/",
 *      description="Applies SoundTouch effects to a live call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/SoundTouchParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/SoundTouchResponse"),
 *          ),
 *      ),
 * )
 */
class SoundTouch implements ControllerInterface
{
    use AuthenticatedTrait;

    public const DEFAULT_AUDIO_DIRECTION = DirectionEnum::Out;

    protected SoundTouchView $view;

    public function __construct()
    {
        $this->view = new SoundTouchView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new SoundTouchResponse();
        $inquiry = SoundTouchInquiry::factory($request);

        return $this->doExecute($request, $response, $inquiry);
    }

    /**
     * Validates API call parameters
     *
     * @param AbstractInquiry $inquiry
     * @param AbstractResponse $response
     */
    public function validate(AbstractInquiry $inquiry, AbstractResponse $response): void
    {
        assert($inquiry instanceof SoundTouchInquiry);
        assert($response instanceof SoundTouchResponse);

        if (!isset($inquiry->CallUUID)) {
            $response->Message = SoundTouchResponse::MESSAGE_NO_CALLUUID;
            $response->Success = false;

            return;
        }

        $inquiry->AudioDirection ??= static::DEFAULT_AUDIO_DIRECTION->value;

        if (!in_array($inquiry->AudioDirection, ['in', 'out'])) {
            $response->Message = SoundTouchResponse::MESSAGE_INVALID_AUDIO_DIRECTION;
            $response->Success = false;

            return;
        }

        if (isset($inquiry->PitchSemiTones)) {
            if (!is_numeric($inquiry->PitchSemiTones)) {
                $response->Message = SoundTouchResponse::MESSAGE_PITCHSEMITONES_NOT_FLOAT;
                $response->Success = false;

                return;
            }

            $inquiry->PitchSemiTones = (float)$inquiry->PitchSemiTones;

            if (($inquiry->PitchSemiTones < -14) || ($inquiry->PitchSemiTones > 14)) {
                $response->Message = SoundTouchResponse::MESSAGE_PITCHSEMITONES_BAD_RANGE;
                $response->Success = false;

                return;
            }
        }

        if (isset($inquiry->PitchOctaves)) {
            if (!is_numeric($inquiry->PitchOctaves)) {
                $response->Message = SoundTouchResponse::MESSAGE_PITCHOCTAVES_NOT_FLOAT;
                $response->Success = false;

                return;
            }

            $inquiry->PitchOctaves = (float)$inquiry->PitchOctaves;

            if (($inquiry->PitchOctaves < -1) || ($inquiry->PitchOctaves > 1)) {
                $response->Message = SoundTouchResponse::MESSAGE_PITCHOCTAVES_BAD_RANGE;
                $response->Success = false;

                return;
            }
        }

        if (isset($inquiry->Pitch)) {
            if (!is_numeric($inquiry->Pitch)) {
                $response->Message = SoundTouchResponse::MESSAGE_PITCH_NOT_FLOAT;
                $response->Success = false;

                return;
            }

            $inquiry->Pitch = (float)$inquiry->Pitch;

            if ($inquiry->Pitch <= 0) {
                $response->Message = SoundTouchResponse::MESSAGE_PITCH_BAD_RANGE;
                $response->Success = false;

                return;
            }
        }

        if (isset($inquiry->Rate)) {
            if (!is_numeric($inquiry->Rate)) {
                $response->Message = SoundTouchResponse::MESSAGE_RATE_NOT_FLOAT;
                $response->Success = false;

                return;
            }

            $inquiry->Rate = (float)$inquiry->Rate;

            if ($inquiry->Rate <= 0) {
                $response->Message = SoundTouchResponse::MESSAGE_RATE_BAD_RANGE;
                $response->Success = false;

                return;
            }
        }

        if (isset($inquiry->Tempo)) {
            if (!is_numeric($inquiry->Tempo)) {
                $response->Message = SoundTouchResponse::MESSAGE_TEMPO_NOT_FLOAT;
                $response->Success = false;

                return;
            }

            $inquiry->Tempo = (float)$inquiry->Tempo;

            if ($inquiry->Tempo <= 0) {
                $response->Message = SoundTouchResponse::MESSAGE_TEMPO_BAD_RANGE;
                $response->Success = false;

                return;
            }
        }

        $channel = $this->app->getChannel($inquiry->CallUUID);

        if (!isset($channel)) {
            $response->Message = SoundTouchResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->channel = $channel;
    }
}
