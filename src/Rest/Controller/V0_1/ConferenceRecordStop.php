<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\ConferenceRecordStop as ConferenceRecordStopInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStop as ConferenceRecordStopResponse;
use RTCKit\Eqivo\Rest\View\V0_1\ConferenceRecordStop as ConferenceRecordStopView;

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
 *      path="/v0.1/ConferenceRecordStop/",
 *      summary="/v0.1/ConferenceRecordStop/",
 *      description="Stops a conference recording",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/ConferenceRecordStopParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/ConferenceRecordStopResponse"),
 *          ),
 *      ),
 * )
 */
class ConferenceRecordStop implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;

    protected ConferenceRecordStopView $view;

    public function __construct()
    {
        $this->view = new ConferenceRecordStopView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = ConferenceRecordStopInquiry::factory($request);
                $response = new ConferenceRecordStopResponse;

                $this->app->restServer->logger->debug('RESTAPI ConferenceRecordStop with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function () use ($response) {
                        return resolve($this->view->execute($response));
                    });
            })
            ->otherwise([$this, 'exceptionHandler']);
    }

    /**
     * Validates API call parameters
     *
     * @param ConferenceRecordStopInquiry $inquiry
     * @param ConferenceRecordStopResponse $response
     */
    public function validate(ConferenceRecordStopInquiry $inquiry, ConferenceRecordStopResponse $response): void
    {
        if (!isset($inquiry->ConferenceName)) {
            $response->Message = ConferenceRecordStopResponse::MESSAGE_NO_CONFERENCE_NAME;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->RecordFile)) {
            $response->Message = ConferenceRecordStopResponse::MESSAGE_NO_RECORD_FILE;
            $response->Success = false;

            return;
        }

        $conference = $this->app->getConference($inquiry->ConferenceName);

        if (!isset($conference)) {
            $response->Message = ConferenceRecordStopResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->core = $conference->core;
    }

    /**
     * Performs the API call function
     *
     * @param ConferenceRecordStopInquiry $inquiry
     * @param ConferenceRecordStopResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(ConferenceRecordStopInquiry $inquiry, ConferenceRecordStopResponse $response): PromiseInterface
    {
        return $inquiry->core->client->api(
                (new ESL\Request\Api())->setParameters("conference {$inquiry->ConferenceName} norecord {$inquiry->RecordFile}")
            )
            ->then (function (ESL\Response\ApiResponse $eslResponse) use ($response): PromiseInterface {
                $body = $eslResponse->getBody();

                /* Cannot use Response::isSuccessful() here as the response is non-standard, e.g.
                 * "Stopped recording file /tmp/test.wav\n+OK Stopped recording 0 files\n"
                 */
                if (is_null($body) || strpos($body, '+OK Stopped recording') === false) {
                    $response->Message = ConferenceRecordStopResponse::MESSAGE_FAILED;
                    $response->Success = false;
                } else {
                    $response->Message = ConferenceRecordStopResponse::MESSAGE_SUCCESS;
                    $response->Success = true;
                }

                return resolve();
            });
    }
}
