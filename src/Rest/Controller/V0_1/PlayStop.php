<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait,
    PlaySoundsTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\PlayStop as PlayStopInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\PlayStop as PlayStopResponse;
use RTCKit\Eqivo\Rest\View\V0_1\PlayStop as PlayStopView;

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
 *      path="/v0.1/PlayStop/",
 *      summary="/v0.1/PlayStop/",
 *      description="Interrupts media playback on a given call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/PlayStopParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/PlayStopResponse"),
 *          ),
 *      ),
 * )
 */
class PlayStop implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;
    use PlaySoundsTrait;

    protected PlayStopView $view;

    public function __construct()
    {
        $this->view = new PlayStopView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = PlayStopInquiry::factory($request);
                $response = new PlayStopResponse;

                $this->app->restServer->logger->debug('RESTAPI PlayStop with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function () use ($response) {
                        $response->Message ??= PlayStopResponse::MESSAGE_SUCCESS;
                        $response->Success ??= true;

                        return resolve($this->view->execute($response));
                    });
            })
            ->otherwise([$this, 'exceptionHandler']);
    }

    /**
     * Validates API call parameters
     *
     * @param PlayStopInquiry $inquiry
     * @param PlayStopResponse $response
     */
    public function validate(PlayStopInquiry $inquiry, PlayStopResponse $response): void
    {
        if (!isset($inquiry->CallUUID)) {
            $response->Message = PlayStopResponse::MESSAGE_NO_CALLUUID;
            $response->Success = false;

            return;
        }

        $session = $this->app->getSession($inquiry->CallUUID);

        if (!isset($session)) {
            $response->Message = PlayStopResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->session = $session;
        $inquiry->core = $session->core;
    }

    /**
     * Performs the API call function
     *
     * @param PlayStopInquiry $inquiry
     * @param PlayStopResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(PlayStopInquiry $inquiry, PlayStopResponse $response): PromiseInterface
    {
        return $this->getDisplaceMediaList($inquiry->core, $inquiry->CallUUID)
            ->then(function (array $stopList) use ($inquiry, $response): PromiseInterface {
                if (!isset($stopList[0])) {
                    $this->app->restServer->logger->warning('PlayStop -- Nothing to stop');

                    return resolve([]);
                }

                $promises = [];

                foreach ($stopList as $target) {
                    $command = "uuid_displace {$inquiry->CallUUID} stop {$target}";

                    $promises[] = $inquiry->core->client->bgApi((new ESL\Request\BgApi())->setParameters($command))
                        ->then(function (ESL\Response $eslResponse) use ($response, $command): PromiseInterface {
                            $uuid = null;

                            if ($eslResponse instanceof ESL\Response\CommandReply) {
                                $uuid = $eslResponse->getHeader('job-uuid');
                            }

                            if (!isset($uuid)) {
                                $response->Message = PlayStopResponse::MESSAGE_FAILED;
                                $response->Success = false;

                                $this->app->restServer->logger->error("PlayStop Failed '{$command}': " . ($eslResponse->getBody() ?? '<null>'));
                            }

                            return resolve();
                        });
                }

                return all($promises);
            });
    }
}
