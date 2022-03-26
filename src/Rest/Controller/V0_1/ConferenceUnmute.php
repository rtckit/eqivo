<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\ConferenceUnmute as ConferenceUnmuteInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\ConferenceUnmute as ConferenceUnmuteResponse;
use RTCKit\Eqivo\Rest\View\V0_1\ConferenceUnmute as ConferenceUnmuteView;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use RTCKit\ESL;
use function React\Promise\{
    all,
    resolve
};

/**
 * @OA\Post(
 *      tags={"Conference"},
 *      path="/v0.1/ConferenceUnmute/",
 *      summary="/v0.1/ConferenceUnmute/",
 *      description="Restores audio from one or more conference members",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/ConferenceUnmuteParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/ConferenceUnmuteResponse"),
 *          ),
 *      ),
 * )
 */
class ConferenceUnmute implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;

    protected ConferenceUnmuteView $view;

    public function __construct()
    {
        $this->view = new ConferenceUnmuteView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = ConferenceUnmuteInquiry::factory($request);
                $response = new ConferenceUnmuteResponse;

                $this->app->restServer->logger->debug('RESTAPI ConferenceUnmute with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function () use ($response) {
                        $response->Message = ConferenceUnmuteResponse::MESSAGE_SUCCESS;
                        $response->Success = true;

                        return resolve($this->view->execute($response));
                    });
            })
            ->otherwise([$this, 'exceptionHandler']);
    }

    /**
     * Validates API call parameters
     *
     * @param ConferenceUnmuteInquiry $inquiry
     * @param ConferenceUnmuteResponse $response
     */
    public function validate(ConferenceUnmuteInquiry $inquiry, ConferenceUnmuteResponse $response): void
    {
        if (!isset($inquiry->ConferenceName)) {
            $response->Message = ConferenceUnmuteResponse::MESSAGE_NO_CONFERENCE_NAME;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->MemberID)) {
            $response->Message = ConferenceUnmuteResponse::MESSAGE_NO_MEMBER_ID;
            $response->Success = false;

            return;
        }

        $conference = $this->app->getConference($inquiry->ConferenceName);

        if (!isset($conference)) {
            $response->Message = ConferenceUnmuteResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->core = $conference->core;
    }

    /**
     * Performs the API call function
     *
     * @param ConferenceUnmuteInquiry $inquiry
     * @param ConferenceUnmuteResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(ConferenceUnmuteInquiry $inquiry, ConferenceUnmuteResponse $response): PromiseInterface
    {
        $members = explode(',', $inquiry->MemberID);
        $promises = [];

        foreach ($members as $member) {
            $promises[] = $inquiry->core->client->api(
                (new ESL\Request\Api())->setParameters("conference {$inquiry->ConferenceName} unmute {$member}")
            )
                ->then (function (ESL\Response\ApiResponse $eslResponse) use ($member, $response): PromiseInterface {
                    if (!$eslResponse->isSuccessful()) {
                        $this->app->restServer->logger->warning('Conference Unmute Failed for ' . $member);
                    } else {
                        $this->app->restServer->logger->debug('Conference Unmute Done for ' . $member);
                        $response->Members[] = $member;
                    }

                    return resolve();
                });
        }

        return all($promises);
    }
}
