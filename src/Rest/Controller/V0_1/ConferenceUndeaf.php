<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\ConferenceUndeaf as ConferenceUndeafInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\ConferenceUndeaf as ConferenceUndeafResponse;
use RTCKit\Eqivo\Rest\View\V0_1\ConferenceUndeaf as ConferenceUndeafView;

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
 *      path="/v0.1/ConferenceUndeaf/",
 *      summary="/v0.1/ConferenceUndeaf/",
 *      description="Restores audio to one or more conference members",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/ConferenceUndeafParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/ConferenceUndeafResponse"),
 *          ),
 *      ),
 * )
 */
class ConferenceUndeaf implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;

    protected ConferenceUndeafView $view;

    public function __construct()
    {
        $this->view = new ConferenceUndeafView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = ConferenceUndeafInquiry::factory($request);
                $response = new ConferenceUndeafResponse;

                $this->app->restServer->logger->debug('RESTAPI ConferenceUndeaf with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function () use ($response) {
                        $response->Message = ConferenceUndeafResponse::MESSAGE_SUCCESS;
                        $response->Success = true;

                        return resolve($this->view->execute($response));
                    });
            })
            ->otherwise([$this, 'exceptionHandler']);
    }

    /**
     * Validates API call parameters
     *
     * @param ConferenceUndeafInquiry $inquiry
     * @param ConferenceUndeafResponse $response
     */
    public function validate(ConferenceUndeafInquiry $inquiry, ConferenceUndeafResponse $response): void
    {
        if (!isset($inquiry->ConferenceName)) {
            $response->Message = ConferenceUndeafResponse::MESSAGE_NO_CONFERENCE_NAME;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->MemberID)) {
            $response->Message = ConferenceUndeafResponse::MESSAGE_NO_MEMBER_ID;
            $response->Success = false;

            return;
        }

        $conference = $this->app->getConference($inquiry->ConferenceName);

        if (!isset($conference)) {
            $response->Message = ConferenceUndeafResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->core = $conference->core;
    }

    /**
     * Performs the API call function
     *
     * @param ConferenceUndeafInquiry $inquiry
     * @param ConferenceUndeafResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(ConferenceUndeafInquiry $inquiry, ConferenceUndeafResponse $response): PromiseInterface
    {
        $members = explode(',', $inquiry->MemberID);
        $promises = [];

        foreach ($members as $member) {
            $promises[] = $inquiry->core->client->api(
                (new ESL\Request\Api())->setParameters("conference {$inquiry->ConferenceName} undeaf {$member}")
            )
                ->then (function (ESL\Response\ApiResponse $eslResponse) use ($member, $response): PromiseInterface {
                    if (!$eslResponse->isSuccessful()) {
                        $this->app->restServer->logger->warning('Conference Undeaf Failed for ' . $member);
                    } else {
                        $this->app->restServer->logger->debug('Conference Undeaf Done for ' . $member);
                        $response->Members[] = $member;
                    }

                    return resolve();
                });
        }

        return all($promises);
    }
}
