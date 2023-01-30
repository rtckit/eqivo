<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
};
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\Eqivo\Rest\Inquiry\V0_1\HangupCall as HangupCallInquiry;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;

use RTCKit\Eqivo\Rest\Response\V0_1\HangupCall as HangupCallResponse;
use RTCKit\Eqivo\Rest\View\V0_1\HangupCall as HangupCallView;

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/HangupCall/",
 *      summary="/v0.1/HangupCall/",
 *      description="Hangs up a specific call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/HangupCallParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/HangupCallResponse"),
 *          ),
 *      ),
 * )
 */
class HangupCall implements ControllerInterface
{
    use AuthenticatedTrait;

    protected HangupCallView $view;

    public function __construct()
    {
        $this->view = new HangupCallView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new HangupCallResponse();
        $inquiry = HangupCallInquiry::factory($request);

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
        assert($inquiry instanceof HangupCallInquiry);
        assert($response instanceof HangupCallResponse);

        if (empty($inquiry->CallUUID) && empty($inquiry->RequestUUID)) {
            $response->Message = HangupCallResponse::MESSAGE_NO_PARAMETERS;
            $response->Success = false;

            return;
        }

        if (!empty($inquiry->CallUUID) && !empty($inquiry->RequestUUID)) {
            $response->Message = HangupCallResponse::MESSAGE_BOTH_PRESENT;
            $response->Success = false;

            return;
        }

        if (!empty($inquiry->CallUUID)) {
            $channel = $this->app->getChannel($inquiry->CallUUID);

            if (!isset($channel)) {
                $this->app->restServer->logger->error("Call Hangup Failed -- CallUUID {$inquiry->CallUUID} not found");

                $response->Message = HangupCallResponse::MESSAGE_FAILED;
                $response->Success = false;

                return;
            }

            $inquiry->channel = $channel;
        } else {
            assert(is_string($inquiry->RequestUUID));

            $job = $this->app->getOriginateJob($inquiry->RequestUUID);

            if (!isset($job)) {
                $this->app->restServer->logger->error("Call Hangup Failed -- RequestUUID {$inquiry->RequestUUID} not found");

                $response->Message = HangupCallResponse::MESSAGE_FAILED;
                $response->Success = false;

                return;
            }

            $inquiry->job = $job;
        }
    }
}
