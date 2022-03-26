<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait,
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\SoundTouchStop as SoundTouchStopInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\SoundTouchStop as SoundTouchStopResponse;
use RTCKit\Eqivo\Rest\View\V0_1\SoundTouchStop as SoundTouchStopView;

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
 *      path="/v0.1/SoundTouchStop/",
 *      summary="/v0.1/SoundTouchStop/",
 *      description="Removes SoundTouch effects from a given call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/SoundTouchStopParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/SoundTouchStopResponse"),
 *          ),
 *      ),
 * )
 */
class SoundTouchStop implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;

    protected SoundTouchStopView $view;

    public function __construct()
    {
        $this->view = new SoundTouchStopView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = SoundTouchStopInquiry::factory($request);
                $response = new SoundTouchStopResponse;

                $this->app->restServer->logger->debug('RESTAPI SoundTouchStop with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function () use ($response) {
                        $response->Message ??= SoundTouchStopResponse::MESSAGE_SUCCESS;
                        $response->Success ??= true;

                        return resolve($this->view->execute($response));
                    });
            })
            ->otherwise([$this, 'exceptionHandler']);
    }

    /**
     * Validates API call parameters
     *
     * @param SoundTouchStopInquiry $inquiry
     * @param SoundTouchStopResponse $response
     */
    public function validate(SoundTouchStopInquiry $inquiry, SoundTouchStopResponse $response): void
    {
        if (!isset($inquiry->CallUUID)) {
            $response->Message = SoundTouchStopResponse::MESSAGE_NO_CALLUUID;
            $response->Success = false;

            return;
        }

        $session = $this->app->getSession($inquiry->CallUUID);

        if (!isset($session)) {
            $response->Message = SoundTouchStopResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->session = $session;
        $inquiry->core = $session->core;
    }

    /**
     * Performs the API call function
     *
     * @param SoundTouchStopInquiry $inquiry
     * @param SoundTouchStopResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(SoundTouchStopInquiry $inquiry, SoundTouchStopResponse $response): PromiseInterface
    {
        $command = "soundtouch {$inquiry->CallUUID} stop";

        return $inquiry->core->client->bgApi(
            (new ESL\Request\BgApi())->setParameters($command)
        )
            ->then(function (ESL\Response $eslResponse) use ($response, $command): PromiseInterface {
                $uuid = null;

                if ($eslResponse instanceof ESL\Response\CommandReply) {
                    $uuid = $eslResponse->getHeader('job-uuid');
                }

                if (!isset($uuid)) {
                    $response->Message = SoundTouchStopResponse::MESSAGE_FAILED;
                    $response->Success = false;

                    $this->app->restServer->logger->error("SoundTouchStop Failed '{$command}' -- JobUUID not received");
                }

                return resolve();
            });
    }
}
