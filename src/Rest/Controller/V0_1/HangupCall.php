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
use RTCKit\Eqivo\Rest\Inquiry\V0_1\HangupCall as HangupCallInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\HangupCall as HangupCallResponse;
use RTCKit\Eqivo\Rest\View\V0_1\HangupCall as HangupCallView;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use RTCKit\ESL;
use function React\Promise\resolve;

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
    use ErrorableTrait;

    protected HangupCallView $view;

    public function __construct()
    {
        $this->view = new HangupCallView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = HangupCallInquiry::factory($request);
                $response = new HangupCallResponse;

                $this->app->restServer->logger->debug('RESTAPI HangupCall with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function (?ESL\Response\ApiResponse $eslResponse = null) use ($response) {
                        if (!isset($eslResponse) || !$eslResponse->isSuccessful()) {
                            $response->Message = HangupCallResponse::MESSAGE_FAILED;
                            $response->Success = false;
                        } else {
                            $response->Message = HangupCallResponse::MESSAGE_SUCCESS;
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
     * @param HangupCallInquiry $inquiry
     * @param HangupCallResponse $response
     */
    public function validate(HangupCallInquiry $inquiry, HangupCallResponse $response): void
    {
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
    }

    /**
     * Performs the API call function
     *
     * @param HangupCallInquiry $inquiry
     * @param HangupCallResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(HangupCallInquiry $inquiry, HangupCallResponse $response): PromiseInterface
    {
        $core = null;

        if (!empty($inquiry->CallUUID)) {
            $session = $this->app->getSession($inquiry->CallUUID);

            if (!isset($session)) {
                $this->app->restServer->logger->error("Call Hangup Failed -- CallUUID {$inquiry->CallUUID} not found");

                return resolve();
            }

            $core = $session->core;
            $hup = 'uuid_kill ' . $inquiry->CallUUID . ' ' . HangupCauseEnum::NORMAL_CLEARING->value;
        } else {
            assert(is_string($inquiry->RequestUUID));

            $callRequest = $this->app->getCallRequest($inquiry->RequestUUID);

            if (!isset($callRequest)) {
                $this->app->restServer->logger->error("Call Hangup Failed -- RequestUUID {$inquiry->RequestUUID} not found");

                return resolve();
            }

            $core = $callRequest->core;
            $hup = 'hupall ' . HangupCauseEnum::NORMAL_CLEARING->value . " {$this->app->config->appPrefix}_request_uuid {$inquiry->RequestUUID}";
        }

        return $core->client->api((new ESL\Request\Api())->setParameters($hup));
    }
}
