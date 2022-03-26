<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\RecordStart as RecordStartInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\RecordStart as RecordStartResponse;
use RTCKit\Eqivo\Rest\View\V0_1\RecordStart as RecordStartView;

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
 *      path="/v0.1/RecordStart/",
 *      summary="/v0.1/RecordStart/",
 *      description="Initiates recording of a given call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/RecordStartParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/RecordStartResponse"),
 *          ),
 *      ),
 * )
 */
class RecordStart implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;

    public const RECORD_FILE_FORMATS = ['wav', 'mp3'];

    public const DEFAULT_RECORD_FORMAT = 'mp3';

    public const DEFAULT_TIME_LIMIT = 60;

    protected RecordStartView $view;

    public function __construct()
    {
        $this->view = new RecordStartView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = RecordStartInquiry::factory($request);
                $response = new RecordStartResponse;

                $this->app->restServer->logger->debug('RESTAPI RecordStart with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function (?ESL\Response\ApiResponse $eslResponse = null) use ($response) {
                        if (!isset($eslResponse) || !$eslResponse->isSuccessful()) {
                            $response->Message = RecordStartResponse::MESSAGE_FAILED;
                            $response->Success = false;
                        } else {
                            $response->Message = RecordStartResponse::MESSAGE_SUCCESS;
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
     * @param RecordStartInquiry $inquiry
     * @param RecordStartResponse $response
     */
    public function validate(RecordStartInquiry $inquiry, RecordStartResponse $response): void
    {
        if (!isset($inquiry->CallUUID)) {
            $response->Message = RecordStartResponse::MESSAGE_NO_CALLUUID;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->FileFormat)) {
            $inquiry->FileFormat = static::DEFAULT_RECORD_FORMAT;
        } else {
            if (!in_array($inquiry->FileFormat, static::RECORD_FILE_FORMATS)) {
                $response->Message = RecordStartResponse::MESSAGE_BAD_FILE_FORMAT . " '" . implode("', '", static::RECORD_FILE_FORMATS) . "'";
                $response->Success = false;

                return;
            }
        }

        if (!isset($inquiry->TimeLimit)) {
            $inquiry->TimeLimit = static::DEFAULT_TIME_LIMIT;
        } else {
            $inquiry->TimeLimit = (int)$inquiry->TimeLimit;

            if ($inquiry->TimeLimit < 1) {
                $response->Message = RecordStartResponse::MESSAGE_INVALID_TIME_LIMIT;
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
            $inquiry->FileName = date('Ymd-His') . '_' . $inquiry->CallUUID;
        }

        $session = $this->app->getSession($inquiry->CallUUID);

        if (!isset($session)) {
            $response->Message = RecordStartResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->session = $session;
        $inquiry->core = $session->core;
    }

    /**
     * Performs the API call function
     *
     * @param RecordStartInquiry $inquiry
     * @param RecordStartResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(RecordStartInquiry $inquiry, RecordStartResponse $response): PromiseInterface
    {
        $response->RecordFile = "{$inquiry->FilePath}{$inquiry->FileName}.{$inquiry->FileFormat}";

        return $inquiry->core->client->api(
            (new ESL\Request\Api())->setParameters("uuid_record {$inquiry->CallUUID} start {$response->RecordFile} {$inquiry->TimeLimit}")
        );
    }
}
