<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\ConferenceKick as ConferenceKickInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\ConferenceKick as ConferenceKickResponse;
use RTCKit\Eqivo\Rest\View\V0_1\ConferenceKick as ConferenceKickView;

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
 *      path="/v0.1/ConferenceKick/",
 *      summary="/v0.1/ConferenceKick/",
 *      description="Kicks one or more conference members",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/ConferenceKickParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/ConferenceKickResponse"),
 *          ),
 *      ),
 * )
 */
class ConferenceKick implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;

    protected ConferenceKickView $view;

    public function __construct()
    {
        $this->view = new ConferenceKickView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = ConferenceKickInquiry::factory($request);
                $response = new ConferenceKickResponse;

                $this->app->restServer->logger->debug('RESTAPI ConferenceKick with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function () use ($response) {
                        $response->Message = ConferenceKickResponse::MESSAGE_SUCCESS;
                        $response->Success = true;

                        return resolve($this->view->execute($response));
                    });
            })
            ->otherwise([$this, 'exceptionHandler']);
    }

    /**
     * Validates API call parameters
     *
     * @param ConferenceKickInquiry $inquiry
     * @param ConferenceKickResponse $response
     */
    public function validate(ConferenceKickInquiry $inquiry, ConferenceKickResponse $response): void
    {
        if (!isset($inquiry->ConferenceName)) {
            $response->Message = ConferenceKickResponse::MESSAGE_NO_CONFERENCE_NAME;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->MemberID)) {
            $response->Message = ConferenceKickResponse::MESSAGE_NO_MEMBER_ID;
            $response->Success = false;

            return;
        }

        $conference = $this->app->getConference($inquiry->ConferenceName);

        if (!isset($conference)) {
            $response->Message = ConferenceKickResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->core = $conference->core;
    }

    /**
     * Performs the API call function
     *
     * @param ConferenceKickInquiry $inquiry
     * @param ConferenceKickResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(ConferenceKickInquiry $inquiry, ConferenceKickResponse $response): PromiseInterface
    {
        $members = explode(',', $inquiry->MemberID);
        $promises = [];

        foreach ($members as $member) {
            $promises[] = $inquiry->core->client->api(
                (new ESL\Request\Api())->setParameters("conference {$inquiry->ConferenceName} kick {$member}")
            )
                ->then (function (ESL\Response\ApiResponse $eslResponse) use ($member, $response): PromiseInterface {
                    if (!$eslResponse->isSuccessful()) {
                        $this->app->restServer->logger->warning('Conference Kick Failed for ' . $member);
                    } else {
                        $this->app->restServer->logger->debug('Conference Kick Done for ' . $member);
                        $response->Members[] = $member;
                    }

                    return resolve();
                });
        }

        return all($promises);
    }
}
