<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\ConferenceRecordStart as ConferenceRecordStartInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStart as ConferenceRecordStartResponse;
use RTCKit\Eqivo\Rest\View\V0_1\ConferenceRecordStart as ConferenceRecordStartView;

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
 *      path="/v0.1/ConferenceRecordStart/",
 *      summary="/v0.1/ConferenceRecordStart/",
 *      description="Initiates a conference recording",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/ConferenceRecordStartParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/ConferenceRecordStartResponse"),
 *          ),
 *      ),
 * )
 */
class ConferenceRecordStart implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;

    public const RECORD_FILE_FORMATS = ['wav', 'mp3'];

    public const DEFAULT_RECORD_FORMAT = 'mp3';

    protected ConferenceRecordStartView $view;

    public function __construct()
    {
        $this->view = new ConferenceRecordStartView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = ConferenceRecordStartInquiry::factory($request);
                $response = new ConferenceRecordStartResponse;

                $this->app->restServer->logger->debug('RESTAPI ConferenceRecordStart with ' . json_encode($inquiry));
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
     * @param ConferenceRecordStartInquiry $inquiry
     * @param ConferenceRecordStartResponse $response
     */
    public function validate(ConferenceRecordStartInquiry $inquiry, ConferenceRecordStartResponse $response): void
    {
        if (!isset($inquiry->ConferenceName)) {
            $response->Message = ConferenceRecordStartResponse::MESSAGE_NO_CONFERENCE_NAME;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->FileFormat)) {
            $inquiry->FileFormat = static::DEFAULT_RECORD_FORMAT;
        } else {
            if (!in_array($inquiry->FileFormat, static::RECORD_FILE_FORMATS)) {
                $response->Message = ConferenceRecordStartResponse::MESSAGE_BAD_FILE_FORMAT . " '" . implode("', '", static::RECORD_FILE_FORMATS) . "'";
                $response->Success = false;

                return;
            }
        }

        if (!isset($inquiry->FilePath)) {
            $inquiry->FilePath = '';
        } else {
            $inquiry->FilePath = rtrim($inquiry->FilePath, '/') . '/';
        }

        if (!isset($inquiry->FileName)) {
            $inquiry->FileName = date('Ymd-His') . '_' . $inquiry->ConferenceName;
        }

        $conference = $this->app->getConference($inquiry->ConferenceName);

        if (!isset($conference)) {
            $response->Message = ConferenceRecordStartResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->core = $conference->core;
    }

    /**
     * Performs the API call function
     *
     * @param ConferenceRecordStartInquiry $inquiry
     * @param ConferenceRecordStartResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(ConferenceRecordStartInquiry $inquiry, ConferenceRecordStartResponse $response): PromiseInterface
    {
        $recordFile = "{$inquiry->FilePath}{$inquiry->FileName}.{$inquiry->FileFormat}";

        return $inquiry->core->client->api(
                (new ESL\Request\Api())->setParameters("conference {$inquiry->ConferenceName} record {$recordFile}")
            )
            ->then (function (ESL\Response\ApiResponse $eslResponse) use ($recordFile, $response): PromiseInterface {
                if (!$eslResponse->isSuccessful()) {
                    $response->Message = ConferenceRecordStartResponse::MESSAGE_FAILED;
                    $response->Success = false;
                } else {
                    $response->Message = ConferenceRecordStartResponse::MESSAGE_SUCCESS;
                    $response->RecordFile = $recordFile;
                    $response->Success = true;
                }

                return resolve();
            });
    }
}
