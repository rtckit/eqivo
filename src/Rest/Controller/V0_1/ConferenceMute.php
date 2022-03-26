<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\ConferenceMute as ConferenceMuteInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\ConferenceMute as ConferenceMuteResponse;
use RTCKit\Eqivo\Rest\View\V0_1\ConferenceMute as ConferenceMuteView;

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
 *      path="/v0.1/ConferenceMute/",
 *      summary="/v0.1/ConferenceMute/",
 *      description="Blocks audio from one or more conference members",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/ConferenceMuteParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/ConferenceMuteResponse"),
 *          ),
 *      ),
 * )
 */
class ConferenceMute implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;

    protected ConferenceMuteView $view;

    public function __construct()
    {
        $this->view = new ConferenceMuteView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = ConferenceMuteInquiry::factory($request);
                $response = new ConferenceMuteResponse;

                $this->app->restServer->logger->debug('RESTAPI ConferenceMute with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function () use ($response) {
                        $response->Message = ConferenceMuteResponse::MESSAGE_SUCCESS;
                        $response->Success = true;

                        return resolve($this->view->execute($response));
                    });
            })
            ->otherwise([$this, 'exceptionHandler']);
    }

    /**
     * Validates API call parameters
     *
     * @param ConferenceMuteInquiry $inquiry
     * @param ConferenceMuteResponse $response
     */
    public function validate(ConferenceMuteInquiry $inquiry, ConferenceMuteResponse $response): void
    {
        if (!isset($inquiry->ConferenceName)) {
            $response->Message = ConferenceMuteResponse::MESSAGE_NO_CONFERENCE_NAME;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->MemberID)) {
            $response->Message = ConferenceMuteResponse::MESSAGE_NO_MEMBER_ID;
            $response->Success = false;

            return;
        }

        $conference = $this->app->getConference($inquiry->ConferenceName);

        if (!isset($conference)) {
            $response->Message = ConferenceMuteResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->core = $conference->core;
    }

    /**
     * Performs the API call function
     *
     * @param ConferenceMuteInquiry $inquiry
     * @param ConferenceMuteResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(ConferenceMuteInquiry $inquiry, ConferenceMuteResponse $response): PromiseInterface
    {
        $members = explode(',', $inquiry->MemberID);
        $promises = [];

        foreach ($members as $member) {
            $promises[] = $inquiry->core->client->api(
                (new ESL\Request\Api())->setParameters("conference {$inquiry->ConferenceName} mute {$member}")
            )
                ->then (function (ESL\Response\ApiResponse $eslResponse) use ($member, $response): PromiseInterface {
                    if (!$eslResponse->isSuccessful()) {
                        $this->app->restServer->logger->warning('Conference Mute Failed for ' . $member);
                    } else {
                        $this->app->restServer->logger->debug('Conference Mute Done for ' . $member);
                        $response->Members[] = $member;
                    }

                    return resolve();
                });
        }

        return all($promises);
    }
}
