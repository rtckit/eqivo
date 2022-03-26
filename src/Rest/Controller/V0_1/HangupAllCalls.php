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
use RTCKit\Eqivo\Rest\Inquiry\V0_1\HangupAllCalls as HangupAllCallsInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\HangupAllCalls as HangupAllCallsResponse;
use RTCKit\Eqivo\Rest\View\V0_1\HangupAllCalls as HangupAllCallsView;

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
 *      path="/v0.1/HangupAllCalls/",
 *      summary="/v0.1/HangupAllCalls/",
 *      description="Hangs up all established calls",
 *      security={{"basicAuth": {}}},
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/HangupAllCallsResponse"),
 *          ),
 *      ),
 * )
 */
class HangupAllCalls implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;

    protected HangupAllCallsView $view;

    public function __construct()
    {
        $this->view = new HangupAllCallsView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = HangupAllCallsInquiry::factory($request);
                $response = new HangupAllCallsResponse;

                $this->app->restServer->logger->debug('RESTAPI HangupAllCalls with ' . json_encode($inquiry));

                return $this->perform($inquiry, $response)
                    ->then(function () use ($response) {
                        $response->Message ??= HangupAllCallsResponse::MESSAGE_SUCCESS;
                        $response->Success ??= true;

                        return resolve($this->view->execute($response));
                    });
            })
            ->otherwise([$this, 'exceptionHandler']);
    }

    /**
     * Performs the API call function
     *
     * @param HangupAllCallsInquiry $inquiry
     * @param HangupAllCallsResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(HangupAllCallsInquiry $inquiry, HangupAllCallsResponse $response): PromiseInterface
    {
        $cores = $this->app->getAllCores();
        $promises = [];

        foreach ($cores as $core) {
            $promises[] = $core->client->bgApi((new ESL\Request\BgApi())->setParameters('hupall ' . HangupCauseEnum::NORMAL_CLEARING->value))
                ->then(function (ESL\Response $eslResponse) use ($response): PromiseInterface {
                    $uuid = null;

                    if ($eslResponse instanceof ESL\Response\CommandReply) {
                        $uuid = $eslResponse->getHeader('job-uuid');
                    }

                    if (!isset($uuid)) {
                        $response->Message = HangupAllCallsResponse::MESSAGE_FAILED;
                        $response->Success = false;
                    }

                    return resolve();
                });
        }

        return all($promises);

    }
}
