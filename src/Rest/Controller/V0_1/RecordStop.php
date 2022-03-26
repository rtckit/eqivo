<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\RecordStop as RecordStopInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\RecordStop as RecordStopResponse;
use RTCKit\Eqivo\Rest\View\V0_1\RecordStop as RecordStopView;

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
 *      path="/v0.1/RecordStop/",
 *      summary="/v0.1/RecordStop/",
 *      description="Stops the recording of a given call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/RecordStopParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/RecordStopResponse"),
 *          ),
 *      ),
 * )
 */
class RecordStop implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;

    protected RecordStopView $view;

    public function __construct()
    {
        $this->view = new RecordStopView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = RecordStopInquiry::factory($request);
                $response = new RecordStopResponse;

                $this->app->restServer->logger->debug('RESTAPI RecordStop with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function (?ESL\Response\ApiResponse $eslResponse = null) use ($response) {
                        if (!isset($eslResponse) || !$eslResponse->isSuccessful()) {
                            $response->Message = RecordStopResponse::MESSAGE_FAILED;
                            $response->Success = false;
                        } else {
                            $response->Message = RecordStopResponse::MESSAGE_SUCCESS;
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
     * @param RecordStopInquiry $inquiry
     * @param RecordStopResponse $response
     */
    public function validate(RecordStopInquiry $inquiry, RecordStopResponse $response): void
    {
        if (!isset($inquiry->CallUUID)) {
            $response->Message = RecordStopResponse::MESSAGE_NO_CALLUUID;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->RecordFile)) {
            $response->Message = RecordStopResponse::MESSAGE_NO_RECORD_FILE;
            $response->Success = false;

            return;
        }

        $session = $this->app->getSession($inquiry->CallUUID);

        if (!isset($session)) {
            $response->Message = RecordStopResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->session = $session;
        $inquiry->core = $session->core;
    }

    /**
     * Performs the API call function
     *
     * @param RecordStopInquiry $inquiry
     * @param RecordStopResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(RecordStopInquiry $inquiry, RecordStopResponse $response): PromiseInterface
    {
        return $inquiry->core->client->api(
            (new ESL\Request\Api())->setParameters("uuid_record {$inquiry->CallUUID} stop {$inquiry->RecordFile}")
        );
    }
}
