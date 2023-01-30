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
use RTCKit\Eqivo\Rest\Inquiry\V0_1\SoundTouchStop as SoundTouchStopInquiry;

use RTCKit\Eqivo\Rest\Response\AbstractResponse;
use RTCKit\Eqivo\Rest\Response\V0_1\SoundTouchStop as SoundTouchStopResponse;

use RTCKit\Eqivo\Rest\View\V0_1\SoundTouchStop as SoundTouchStopView;

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/SoundTouchStop/",
 *      summary="/v0.1/SoundTouchStop/",
 *      description="Removes SoundTouch effects from a given call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/SoundTouchStopParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/SoundTouchStopResponse"),
 *          ),
 *      ),
 * )
 */
class SoundTouchStop implements ControllerInterface
{
    use AuthenticatedTrait;

    protected SoundTouchStopView $view;

    public function __construct()
    {
        $this->view = new SoundTouchStopView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new SoundTouchStopResponse();
        $inquiry = SoundTouchStopInquiry::factory($request);

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
        assert($inquiry instanceof SoundTouchStopInquiry);
        assert($response instanceof SoundTouchStopResponse);

        if (!isset($inquiry->CallUUID)) {
            $response->Message = SoundTouchStopResponse::MESSAGE_NO_CALLUUID;
            $response->Success = false;

            return;
        }

        $channel = $this->app->getChannel($inquiry->CallUUID);

        if (!isset($channel)) {
            $response->Message = SoundTouchStopResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->channel = $channel;
    }
}
