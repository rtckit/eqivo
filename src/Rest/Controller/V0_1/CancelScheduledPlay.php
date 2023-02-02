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
use RTCKit\Eqivo\Rest\Inquiry\V0_1\CancelScheduledPlay as CancelScheduledPlayInquiry;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;
use RTCKit\Eqivo\Rest\Response\V0_1\CancelScheduledPlay as CancelScheduledPlayResponse;

use RTCKit\Eqivo\Rest\View\V0_1\CancelScheduledPlay as CancelScheduledPlayView;
use RTCKit\Eqivo\{
    AbstractApp,
    PlayCauseEnum
};

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/CancelScheduledPlay/",
 *      summary="/v0.1/CancelScheduledPlay/",
 *      description="Cancels a scheduled playback request",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/CancelScheduledPlayParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/CancelScheduledPlayResponse"),
 *          ),
 *      ),
 * )
 */
class CancelScheduledPlay implements ControllerInterface
{
    use AuthenticatedTrait;

    protected CancelScheduledPlayView $view;

    public function __construct()
    {
        $this->view = new CancelScheduledPlayView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new CancelScheduledPlayResponse();
        $inquiry = CancelScheduledPlayInquiry::factory($request);

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
        assert($inquiry instanceof CancelScheduledPlayInquiry);
        assert($response instanceof CancelScheduledPlayResponse);

        if (!isset($inquiry->SchedPlayId)) {
            $response->Message = CancelScheduledPlayResponse::MESSAGE_NO_SCHEDPLAYID;
            $response->Success = false;

            return;
        }

        $schedPlay = $this->app->getScheduledPlay($inquiry->SchedPlayId);

        if (!isset($schedPlay)) {
            $response->Message = CancelScheduledPlayResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->play = $schedPlay;
    }
}
