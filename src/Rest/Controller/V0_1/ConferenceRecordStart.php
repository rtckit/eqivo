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
use RTCKit\Eqivo\Rest\Inquiry\V0_1\ConferenceRecordStart as ConferenceRecordStartInquiry;

use RTCKit\Eqivo\Rest\Response\AbstractResponse;
use RTCKit\Eqivo\Rest\Response\V0_1\ConferenceRecordStart as ConferenceRecordStartResponse;

use RTCKit\Eqivo\Rest\View\V0_1\ConferenceRecordStart as ConferenceRecordStartView;
use RTCKit\Eqivo\TypeHelper;

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

    public const RECORD_FILE_FORMATS = ['wav', 'mp3'];

    public const DEFAULT_RECORD_FORMAT = 'mp3';

    protected ConferenceRecordStartView $view;

    public function __construct()
    {
        $this->view = new ConferenceRecordStartView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new ConferenceRecordStartResponse();
        $inquiry = ConferenceRecordStartInquiry::factory($request);

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
        assert($inquiry instanceof ConferenceRecordStartInquiry);
        assert($response instanceof ConferenceRecordStartResponse);

        if (!isset($inquiry->ConferenceName)) {
            $response->Message = ConferenceRecordStartResponse::MESSAGE_NO_CONFERENCE_NAME;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->FileFormat)) {
            /** @phpstan-ignore assign.propertyType */
            $inquiry->FileFormat = static::DEFAULT_RECORD_FORMAT;
        } else {
            $fileFormat = TypeHelper::toString($inquiry->FileFormat);
            $inquiry->FileFormat = $fileFormat;
            $formats = static::RECORD_FILE_FORMATS;
            if (is_array($formats) && !in_array($fileFormat, $formats, true)) {
                $response->Message = ConferenceRecordStartResponse::MESSAGE_BAD_FILE_FORMAT . " '" . implode("', '", $formats) . "'";
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

        $conference = $this->app->getConferenceByRoom($inquiry->ConferenceName);

        if (!isset($conference)) {
            $response->Message = ConferenceRecordStartResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->core = $conference->core;
    }
}
