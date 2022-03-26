<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\{
    App,
    HangupCauseEnum
};
use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\CancelScheduledHangup as CancelScheduledHangupInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\CancelScheduledHangup as CancelScheduledHangupResponse;
use RTCKit\Eqivo\Rest\View\V0_1\CancelScheduledHangup as CancelScheduledHangupView;

use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use React\Promise\PromiseInterface;
use RTCKit\ESL;
use function React\Promise\resolve;

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/CancelScheduledHangup/",
 *      summary="/v0.1/CancelScheduledHangup/",
 *      description="Cancels a scheduled hangup for a call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/CancelScheduledHangupParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/CancelScheduledHangupResponse"),
 *          ),
 *      ),
 * )
 */
class CancelScheduledHangup implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;

    protected CancelScheduledHangupView $view;

    public function __construct()
    {
        $this->view = new CancelScheduledHangupView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = CancelScheduledHangupInquiry::factory($request);
                $response = new CancelScheduledHangupResponse;

                $this->app->restServer->logger->debug('RESTAPI CancelScheduledHangup with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function (?ESL\Response\ApiResponse $eslResponse = null) use ($response) {
                        if (!isset($eslResponse) || !$eslResponse->isSuccessful()) {
                            $response->Message = CancelScheduledHangupResponse::MESSAGE_FAILED;
                            $response->Success = false;
                        } else {
                            $response->Message = CancelScheduledHangupResponse::MESSAGE_SUCCESS;
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
     * @param CancelScheduledHangupInquiry $inquiry
     * @param CancelScheduledHangupResponse $response
     */
    public function validate(CancelScheduledHangupInquiry $inquiry, CancelScheduledHangupResponse $response): void
    {
        if (!isset($inquiry->SchedHangupId)) {
            $response->Message = CancelScheduledHangupResponse::MESSAGE_NO_SCHEDHUPID;
            $response->Success = false;

            return;
        }

        $schedHup = $this->app->getScheduledHangup($inquiry->SchedHangupId);

        if (!isset($schedHup)) {
            $response->Message = CancelScheduledHangupResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->hup = $schedHup;
        $inquiry->core = $schedHup->core;
    }

    /**
     * Performs the API call function
     *
     * @param CancelScheduledHangupInquiry $inquiry
     * @param CancelScheduledHangupResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(CancelScheduledHangupInquiry $inquiry, CancelScheduledHangupResponse $response): PromiseInterface
    {
        return $inquiry->core->client->api((new ESL\Request\Api())->setParameters("sched_del {$inquiry->hup->uuid}"));
    }
}
