<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\ConferencePlay as ConferencePlayInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\ConferencePlay as ConferencePlayResponse;
use RTCKit\Eqivo\Rest\View\V0_1\ConferencePlay as ConferencePlayView;

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
 *      path="/v0.1/ConferencePlay/",
 *      summary="/v0.1/ConferencePlay/",
 *      description="Plays media to one or more conference members",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/ConferencePlayParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/ConferencePlayResponse"),
 *          ),
 *      ),
 * )
 */
class ConferencePlay implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;

    protected ConferencePlayView $view;

    public function __construct()
    {
        $this->view = new ConferencePlayView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = ConferencePlayInquiry::factory($request);
                $response = new ConferencePlayResponse;

                $this->app->restServer->logger->debug('RESTAPI ConferencePlay with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function (?ESL\Response\ApiResponse $eslResponse = null) use ($response) {
                        if (!isset($eslResponse) || !$eslResponse->isSuccessful()) {
                            $response->Message = ConferencePlayResponse::MESSAGE_FAILED;
                            $response->Success = false;
                        } else {
                            $response->Message = ConferencePlayResponse::MESSAGE_SUCCESS;
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
     * @param ConferencePlayInquiry $inquiry
     * @param ConferencePlayResponse $response
     */
    public function validate(ConferencePlayInquiry $inquiry, ConferencePlayResponse $response): void
    {
        if (!isset($inquiry->ConferenceName)) {
            $response->Message = ConferencePlayResponse::MESSAGE_NO_CONFERENCE_NAME;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->FilePath)) {
            $response->Message = ConferencePlayResponse::MESSAGE_NO_FILEPATH;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->MemberID)) {
            $response->Message = ConferencePlayResponse::MESSAGE_NO_MEMBER_ID;
            $response->Success = false;

            return;
        }

        $conference = $this->app->getConference($inquiry->ConferenceName);

        if (!isset($conference)) {
            $response->Message = ConferencePlayResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->core = $conference->core;
    }

    /**
     * Performs the API call function
     *
     * @param ConferencePlayInquiry $inquiry
     * @param ConferencePlayResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(ConferencePlayInquiry $inquiry, ConferencePlayResponse $response): PromiseInterface
    {
        $cmd = "conference {$inquiry->ConferenceName} play '{$inquiry->FilePath}' ";

        if ($inquiry->MemberID === 'all') {
            $cmd .= 'async';
        } else {
            $cmd .= $inquiry->MemberID;
        }

        return $inquiry->core->client->api((new ESL\Request\Api())->setParameters($cmd));
    }
}
