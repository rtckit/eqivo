<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
};
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\Eqivo\Rest\Inquiry\V0_1\CancelScheduledHangup as CancelScheduledHangupInquiry;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;
use RTCKit\Eqivo\Rest\Response\V0_1\CancelScheduledHangup as CancelScheduledHangupResponse;

use RTCKit\Eqivo\Rest\View\V0_1\CancelScheduledHangup as CancelScheduledHangupView;
use RTCKit\Eqivo\{
    AbstractApp,
    HangupCauseEnum
};

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/CancelScheduledHangup/",
 *      summary="/v0.1/CancelScheduledHangup/",
 *      description="Cancels a scheduled hangup for a call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/CancelScheduledHangupParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/CancelScheduledHangupResponse"),
 *          ),
 *      ),
 * )
 */
class CancelScheduledHangup implements ControllerInterface
{
    use AuthenticatedTrait;

    protected CancelScheduledHangupView $view;

    public function __construct()
    {
        $this->view = new CancelScheduledHangupView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new CancelScheduledHangupResponse();
        $inquiry = CancelScheduledHangupInquiry::factory($request);

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
        assert($inquiry instanceof CancelScheduledHangupInquiry);
        assert($response instanceof CancelScheduledHangupResponse);

        if (!isset($inquiry->SchedHangupId)) {
            $response->Message = CancelScheduledHangupResponse::MESSAGE_NO_SCHEDHUPID;
            $response->Success = false;

            return;
        }

        $schedHup = $this->app->getScheduledHangup($inquiry->SchedHangupId);

        if (!isset($schedHup)) {
            $response->Message = CancelScheduledHangupResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->hup = $schedHup;
    }
}
