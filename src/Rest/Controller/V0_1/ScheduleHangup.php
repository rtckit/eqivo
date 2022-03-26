<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\{
    App,
    HangupCauseEnum,
    ScheduledHangup
};
use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\ScheduleHangup as ScheduleHangupInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\ScheduleHangup as ScheduleHangupResponse;
use RTCKit\Eqivo\Rest\View\V0_1\ScheduleHangup as ScheduleHangupView;

use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use React\Promise\PromiseInterface;
use RTCKit\ESL;
use function React\Promise\resolve;

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
    use ErrorableTrait;

    protected ScheduleHangupView $view;

    public function __construct()
    {
        $this->view = new ScheduleHangupView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = ScheduleHangupInquiry::factory($request);
                $response = new ScheduleHangupResponse;

                $this->app->restServer->logger->debug('RESTAPI ScheduleHangup with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function (?ESL\Response\ApiResponse $eslResponse = null) use ($response) {
                        if (!isset($eslResponse) || !$eslResponse->isSuccessful()) {
                            $response->Message = ScheduleHangupResponse::MESSAGE_FAILED;
                            $response->Success = false;
                        } else {
                            $response->Message = ScheduleHangupResponse::MESSAGE_SUCCESS;
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
     * @param ScheduleHangupInquiry $inquiry
     * @param ScheduleHangupResponse $response
     */
    public function validate(ScheduleHangupInquiry $inquiry, ScheduleHangupResponse $response): void
    {
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

        $session = $this->app->getSession($inquiry->CallUUID);

        if (!isset($session)) {
            $response->Message = ScheduleHangupResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->session = $session;
        $inquiry->core = $session->core;
    }

    /**
     * Performs the API call function
     *
     * @param ScheduleHangupInquiry $inquiry
     * @param ScheduleHangupResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(ScheduleHangupInquiry $inquiry, ScheduleHangupResponse $response): PromiseInterface
    {
        $schedHup = new ScheduledHangup;
        $schedHup->uuid = Uuid::uuid4()->toString();
        $schedHup->timeout = (int)$inquiry->Time;
        $response->SchedHangupId = $schedHup->uuid;

        $inquiry->core->addScheduledHangup($schedHup);

        return $inquiry->core->client->api(
            (new ESL\Request\Api())->setParameters(
                "sched_api +{$inquiry->Time} {$response->SchedHangupId} uuid_kill {$inquiry->CallUUID} " .
                HangupCauseEnum::ALLOTTED_TIMEOUT->value
            )
        );
    }
}
