<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\SoundTouch as SoundTouchInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\SoundTouch as SoundTouchResponse;
use RTCKit\Eqivo\Rest\View\V0_1\SoundTouch as SoundTouchView;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use RTCKit\ESL;
use function React\Promise\{
    all,
    resolve
};

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
    use ErrorableTrait;

    public const DEFAULT_AUDIO_DIRECTION = 'out';

    protected SoundTouchView $view;

    public function __construct()
    {
        $this->view = new SoundTouchView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = SoundTouchInquiry::factory($request);
                $response = new SoundTouchResponse;

                $this->app->restServer->logger->debug('RESTAPI SoundTouch with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function (?ESL\Response\ApiResponse $eslResponse = null) use ($response) {
                        if (!isset($eslResponse) || !$eslResponse->isSuccessful()) {
                            $response->Message = SoundTouchResponse::MESSAGE_FAILED;
                            $response->Success = false;
                        } else {
                            $response->Message = SoundTouchResponse::MESSAGE_SUCCESS;
                            $response->Success = true;
                        }

                        return resolve($this->view->execute($response));
                    });
            })
            ->otherwise([$this, 'exceptionHandler']);
    }

    /**
     * Validates API call parameters
     *
     * @param SoundTouchInquiry $inquiry
     * @param SoundTouchResponse $response
     */
    public function validate(SoundTouchInquiry $inquiry, SoundTouchResponse $response): void
    {
        if (!isset($inquiry->CallUUID)) {
            $response->Message = SoundTouchResponse::MESSAGE_NO_CALLUUID;
            $response->Success = false;

            return;
        }

        $inquiry->AudioDirection ??= static::DEFAULT_AUDIO_DIRECTION;

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

        $session = $this->app->getSession($inquiry->CallUUID);

        if (!isset($session)) {
            $response->Message = SoundTouchResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->session = $session;
        $inquiry->core = $session->core;
    }

    /**
     * Performs the API call function
     *
     * @param SoundTouchInquiry $inquiry
     * @param SoundTouchResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(SoundTouchInquiry $inquiry, SoundTouchResponse $response): PromiseInterface
    {
        return $inquiry->core->client->api(
            (new ESL\Request\Api())->setParameters("soundtouch {$inquiry->CallUUID} stop")
        )
            ->then(function () use ($inquiry): PromiseInterface {
                $cmd = "soundtouch {$inquiry->CallUUID} start";

                if ($inquiry->AudioDirection === 'in') {
                    $cmd .= ' send_leg';
                }

                if (isset($inquiry->PitchSemiTones)) {
                    $cmd .= " {$inquiry->PitchSemiTones}s";
                }

                if (isset($inquiry->PitchOctaves)) {
                    $cmd .= " {$inquiry->PitchOctaves}o";
                }

                if (isset($inquiry->Pitch)) {
                    $cmd .= " {$inquiry->Pitch}p";
                }

                if (isset($inquiry->Rate)) {
                    $cmd .= " {$inquiry->Rate}r";
                }

                if (isset($inquiry->Tempo)) {
                    $cmd .= " {$inquiry->Tempo}t";
                }

                return $inquiry->core->client->api((new ESL\Request\Api())->setParameters($cmd));
            });
    }
}
