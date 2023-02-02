<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use function React\Promise\{
    all,
    resolve
};
use RTCKit\FiCore\Command\Channel\Playback;
use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
};
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;

use RTCKit\Eqivo\Rest\Inquiry\V0_1\PlayStop as PlayStopInquiry;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;
use RTCKit\Eqivo\Rest\Response\V0_1\PlayStop as PlayStopResponse;

use RTCKit\Eqivo\Rest\View\V0_1\PlayStop as PlayStopView;

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/PlayStop/",
 *      summary="/v0.1/PlayStop/",
 *      description="Interrupts media playback on a given call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/PlayStopParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/PlayStopResponse"),
 *          ),
 *      ),
 * )
 */
class PlayStop implements ControllerInterface
{
    use AuthenticatedTrait;

    protected PlayStopView $view;

    public function __construct()
    {
        $this->view = new PlayStopView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new PlayStopResponse();
        $inquiry = PlayStopInquiry::factory($request);

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
        assert($inquiry instanceof PlayStopInquiry);
        assert($response instanceof PlayStopResponse);

        if (!isset($inquiry->CallUUID)) {
            $response->Message = PlayStopResponse::MESSAGE_NO_CALLUUID;
            $response->Success = false;

            return;
        }

        $channel = $this->app->getChannel($inquiry->CallUUID);

        if (!isset($channel)) {
            $response->Message = PlayStopResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->channel = $channel;
    }
}
