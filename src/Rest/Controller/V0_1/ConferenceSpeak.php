<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\ConferenceSpeak as ConferenceSpeakInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\ConferenceSpeak as ConferenceSpeakResponse;
use RTCKit\Eqivo\Rest\View\V0_1\ConferenceSpeak as ConferenceSpeakView;

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
 *      path="/v0.1/ConferenceSpeak/",
 *      summary="/v0.1/ConferenceSpeak/",
 *      description="Plays synthesized speech into a conference",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/ConferenceSpeakParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/ConferenceSpeakResponse"),
 *          ),
 *      ),
 * )
 */
class ConferenceSpeak implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;

    protected ConferenceSpeakView $view;

    public function __construct()
    {
        $this->view = new ConferenceSpeakView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = ConferenceSpeakInquiry::factory($request);
                $response = new ConferenceSpeakResponse;

                $this->app->restServer->logger->debug('RESTAPI ConferenceSpeak with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function (?ESL\Response\ApiResponse $eslResponse = null) use ($response) {
                        if (!isset($eslResponse) || !$eslResponse->isSuccessful()) {
                            $response->Message = ConferenceSpeakResponse::MESSAGE_FAILED;
                            $response->Success = false;
                        } else {
                            $response->Message = ConferenceSpeakResponse::MESSAGE_SUCCESS;
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
     * @param ConferenceSpeakInquiry $inquiry
     * @param ConferenceSpeakResponse $response
     */
    public function validate(ConferenceSpeakInquiry $inquiry, ConferenceSpeakResponse $response): void
    {
        if (!isset($inquiry->ConferenceName)) {
            $response->Message = ConferenceSpeakResponse::MESSAGE_NO_CONFERENCE_NAME;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->Text)) {
            $response->Message = ConferenceSpeakResponse::MESSAGE_NO_TEXT;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->MemberID)) {
            $response->Message = ConferenceSpeakResponse::MESSAGE_NO_MEMBER_ID;
            $response->Success = false;

            return;
        }

        $conference = $this->app->getConference($inquiry->ConferenceName);

        if (!isset($conference)) {
            $response->Message = ConferenceSpeakResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->core = $conference->core;
    }

    /**
     * Performs the API call function
     *
     * @param ConferenceSpeakInquiry $inquiry
     * @param ConferenceSpeakResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(ConferenceSpeakInquiry $inquiry, ConferenceSpeakResponse $response): PromiseInterface
    {
        $cmd = "conference {$inquiry->ConferenceName} say";

        if ($inquiry->MemberID === 'all') {
            $cmd .= " '{$inquiry->Text}'";
        } else {
            $cmd .= "member {$inquiry->MemberID} '{$inquiry->Text}'";
        }

        return $inquiry->core->client->api((new ESL\Request\Api())->setParameters($cmd));
    }
}
