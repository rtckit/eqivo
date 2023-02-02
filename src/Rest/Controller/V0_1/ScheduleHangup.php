<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use RTCKit\FiCore\Command\Channel\Hangup;
use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
};
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\Eqivo\Rest\Inquiry\V0_1\ScheduleHangup as ScheduleHangupInquiry;

use RTCKit\Eqivo\Rest\Response\AbstractResponse;
use RTCKit\Eqivo\Rest\Response\V0_1\ScheduleHangup as ScheduleHangupResponse;
use RTCKit\Eqivo\Rest\View\V0_1\ScheduleHangup as ScheduleHangupView;
use RTCKit\FiCore\Switch\{
    HangupCauseEnum,
    ScheduledHangup,
};

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/ScheduleHangup/",
 *      summary="/v0.1/ScheduleHangup/",
 *      description="Schedules a hangup for a specific call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/ScheduleHangupParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/ScheduleHangupResponse"),
 *          ),
 *      ),
 * )
 */
class ScheduleHangup implements ControllerInterface
{
    use AuthenticatedTrait;

    protected ScheduleHangupView $view;

    public function __construct()
    {
        $this->view = new ScheduleHangupView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new ScheduleHangupResponse();
        $inquiry = ScheduleHangupInquiry::factory($request);

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
        assert($inquiry instanceof ScheduleHangupInquiry);
        assert($response instanceof ScheduleHangupResponse);

        if (!isset($inquiry->CallUUID)) {
            $response->Message = ScheduleHangupResponse::MESSAGE_NO_CALLUUID;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->Time)) {
            $response->Message = ScheduleHangupResponse::MESSAGE_NO_TIME;
            $response->Success = false;

            return;
        }

        $inquiry->Time = (int)$inquiry->Time;

        if ($inquiry->Time <= 0) {
            $response->Message = ScheduleHangupResponse::MESSAGE_NEGATIVE_TIME;
            $response->Success = false;

            return;
        }

        assert(is_string($inquiry->CallUUID));

        $channel = $this->app->getChannel($inquiry->CallUUID);

        if (!isset($channel)) {
            $response->Message = ScheduleHangupResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->channel = $channel;
    }
}
