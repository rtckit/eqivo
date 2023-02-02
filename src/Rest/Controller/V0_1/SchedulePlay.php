<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use function React\Promise\{
    all,
    resolve
};
use RTCKit\FiCore\Command\Channel\Playback;
use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
};
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;

use RTCKit\Eqivo\Rest\Inquiry\V0_1\SchedulePlay as SchedulePlayInquiry;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;
use RTCKit\Eqivo\Rest\Response\V0_1\SchedulePlay as SchedulePlayResponse;

use RTCKit\Eqivo\Rest\View\V0_1\SchedulePlay as SchedulePlayView;

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/SchedulePlay/",
 *      summary="/v0.1/SchedulePlay/",
 *      description="Schedules media playback for a specific call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/SchedulePlayParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/SchedulePlayResponse"),
 *          ),
 *      ),
 * )
 */
class SchedulePlay implements ControllerInterface
{
    use AuthenticatedTrait;

    public const DEFAULT_LENGTH = 3600;

    public const DEFAULT_LEG = 'aleg';

    public const DEFAULT_DELIMITER = ',';

    protected SchedulePlayView $view;

    public function __construct()
    {
        $this->view = new SchedulePlayView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new SchedulePlayResponse();
        $inquiry = SchedulePlayInquiry::factory($request);

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
        assert($inquiry instanceof SchedulePlayInquiry);
        assert($response instanceof SchedulePlayResponse);

        $inquiry->Loop = isset($inquiry->Loop) ? ($inquiry->Loop === 'true') : false;
        $inquiry->Mix = isset($inquiry->Mix) ? ($inquiry->Mix === 'false') : true;

        if (!isset($inquiry->CallUUID)) {
            $response->Message = SchedulePlayResponse::MESSAGE_NO_CALLUUID;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->Sounds)) {
            $response->Message = SchedulePlayResponse::MESSAGE_NO_SOUNDS;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->Time)) {
            $response->Message = SchedulePlayResponse::MESSAGE_NO_TIME;
            $response->Success = false;

            return;
        }

        $inquiry->Time = (int)$inquiry->Time;

        if ($inquiry->Time < 1) {
            $response->Message = SchedulePlayResponse::MESSAGE_INVALID_TIME;
            $response->Success = false;

            return;
        }

        $inquiry->Legs ??= static::DEFAULT_LEG;

        if (!in_array($inquiry->Legs, ['aleg', 'bleg', 'both'])) {
            $response->Message = SchedulePlayResponse::MESSAGE_INVALID_LEG;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->Length)) {
            $inquiry->Length = static::DEFAULT_LENGTH;
        } else {
            $inquiry->Length = (int)$inquiry->Length;

            if ($inquiry->Length < 1) {
                $response->Message = SchedulePlayResponse::MESSAGE_INVALID_LENGTH;
                $response->Success = false;

                return;
            }
        }

        $inquiry->delimiter ??= static::DEFAULT_DELIMITER;
        $inquiry->soundList = explode($inquiry->delimiter, $inquiry->Sounds);

        if (!isset($inquiry->soundList[0])) {
            $response->Message = SchedulePlayResponse::MESSAGE_INVALID_SOUNDS;
            $response->Success = false;
        }

        /** @psalm-suppress PropertyTypeCoercion */
        array_walk($inquiry->soundList, function (string &$entry) {
            if (strpos($entry, 'http_cache://') === 0) {
                $entry = str_replace(' ', '%20', $entry);
            }
        });

        $inquiry->aLegFlags = $inquiry->bLegFlags = '';

        if ($inquiry->Loop) {
            $inquiry->aLegFlags .= 'l';
            $inquiry->bLegFlags .= 'l';
        }

        if ($inquiry->Mix) {
            $inquiry->aLegFlags .= 'm';
            $inquiry->bLegFlags .= 'mr';
        } else {
            $inquiry->bLegFlags .= 'r';
        }

        $channel = $this->app->getChannel($inquiry->CallUUID);

        if (!isset($channel)) {
            $response->Message = SchedulePlayResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->channel = $channel;
    }
}
