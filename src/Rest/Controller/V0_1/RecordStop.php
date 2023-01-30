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
use RTCKit\Eqivo\Rest\Inquiry\V0_1\RecordStop as RecordStopInquiry;

use RTCKit\Eqivo\Rest\Response\AbstractResponse;
use RTCKit\Eqivo\Rest\Response\V0_1\RecordStop as RecordStopResponse;

use RTCKit\Eqivo\Rest\View\V0_1\RecordStop as RecordStopView;

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/RecordStop/",
 *      summary="/v0.1/RecordStop/",
 *      description="Stops the recording of a given call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/RecordStopParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/RecordStopResponse"),
 *          ),
 *      ),
 * )
 */
class RecordStop implements ControllerInterface
{
    use AuthenticatedTrait;

    protected RecordStopView $view;

    public function __construct()
    {
        $this->view = new RecordStopView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new RecordStopResponse();
        $inquiry = RecordStopInquiry::factory($request);

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
        assert($inquiry instanceof RecordStopInquiry);
        assert($response instanceof RecordStopResponse);

        if (!isset($inquiry->CallUUID)) {
            $response->Message = RecordStopResponse::MESSAGE_NO_CALLUUID;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->RecordFile)) {
            $response->Message = RecordStopResponse::MESSAGE_NO_RECORD_FILE;
            $response->Success = false;

            return;
        }

        $channel = $this->app->getChannel($inquiry->CallUUID);

        if (!isset($channel)) {
            $response->Message = RecordStopResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->channel = $channel;
    }
}
