<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\{
    App,
    PlayCauseEnum
};
use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\CancelScheduledPlay as CancelScheduledPlayInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\CancelScheduledPlay as CancelScheduledPlayResponse;
use RTCKit\Eqivo\Rest\View\V0_1\CancelScheduledPlay as CancelScheduledPlayView;

use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use React\Promise\PromiseInterface;
use RTCKit\ESL;
use function React\Promise\resolve;

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/CancelScheduledPlay/",
 *      summary="/v0.1/CancelScheduledPlay/",
 *      description="Cancels a scheduled playback request",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/CancelScheduledPlayParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/CancelScheduledPlayResponse"),
 *          ),
 *      ),
 * )
 */
class CancelScheduledPlay implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;

    protected CancelScheduledPlayView $view;

    public function __construct()
    {
        $this->view = new CancelScheduledPlayView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = CancelScheduledPlayInquiry::factory($request);
                $response = new CancelScheduledPlayResponse;

                $this->app->restServer->logger->debug('RESTAPI CancelScheduledPlay with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function (?ESL\Response\ApiResponse $eslResponse = null) use ($response) {
                        if (!isset($eslResponse) || !$eslResponse->isSuccessful()) {
                            $response->Message = CancelScheduledPlayResponse::MESSAGE_FAILED;
                            $response->Success = false;
                        } else {
                            $response->Message = CancelScheduledPlayResponse::MESSAGE_SUCCESS;
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
     * @param CancelScheduledPlayInquiry $inquiry
     * @param CancelScheduledPlayResponse $response
     */
    public function validate(CancelScheduledPlayInquiry $inquiry, CancelScheduledPlayResponse $response): void
    {
        if (!isset($inquiry->SchedPlayId)) {
            $response->Message = CancelScheduledPlayResponse::MESSAGE_NO_SCHEDPLAYID;
            $response->Success = false;

            return;
        }

        $schedPlay = $this->app->getScheduledPlay($inquiry->SchedPlayId);

        if (!isset($schedPlay)) {
            $response->Message = CancelScheduledPlayResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->play = $schedPlay;
        $inquiry->core = $schedPlay->core;
    }

    /**
     * Performs the API call function
     *
     * @param CancelScheduledPlayInquiry $inquiry
     * @param CancelScheduledPlayResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(CancelScheduledPlayInquiry $inquiry, CancelScheduledPlayResponse $response): PromiseInterface
    {
        return $inquiry->core->client->api((new ESL\Request\Api())->setParameters("sched_del {$inquiry->play->uuid}"));
    }
}
