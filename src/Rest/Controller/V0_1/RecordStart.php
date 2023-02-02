<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use function React\Promise\{
    all,
    resolve
};
use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
};
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\Eqivo\Rest\Inquiry\V0_1\RecordStart as RecordStartInquiry;

use RTCKit\Eqivo\Rest\Response\AbstractResponse;
use RTCKit\Eqivo\Rest\Response\V0_1\RecordStart as RecordStartResponse;

use RTCKit\Eqivo\Rest\View\V0_1\RecordStart as RecordStartView;

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

    public const RECORD_FILE_FORMATS = ['wav', 'mp3'];

    public const DEFAULT_RECORD_FORMAT = 'mp3';

    public const DEFAULT_TIME_LIMIT = 60;

    protected RecordStartView $view;

    public function __construct()
    {
        $this->view = new RecordStartView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new RecordStartResponse();
        $inquiry = RecordStartInquiry::factory($request);

        return $this->doExecute($request, $response, $inquiry);
    }

    /**
     * Validates API call parameters
     *
     * @param AbstractInquiry $inquiry
     * @param AbstractResponse $response
     */
    public function validate(AbstractInquiry $inquiry, AbstractResponse $response): void
    {
        assert($inquiry instanceof RecordStartInquiry);
        assert($response instanceof RecordStartResponse);

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

        $channel = $this->app->getChannel($inquiry->CallUUID);

        if (!isset($channel)) {
            $response->Message = RecordStartResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->channel = $channel;
    }
}
