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

use RTCKit\Eqivo\Rest\Inquiry\V0_1\HangupAllCalls as HangupAllCallsInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\HangupAllCalls as HangupAllCallsResponse;

use RTCKit\Eqivo\Rest\View\V0_1\HangupAllCalls as HangupAllCallsView;

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/HangupAllCalls/",
 *      summary="/v0.1/HangupAllCalls/",
 *      description="Hangs up all established calls",
 *      security={{"basicAuth": {}}},
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/HangupAllCallsResponse"),
 *          ),
 *      ),
 * )
 */
class HangupAllCalls implements ControllerInterface
{
    use AuthenticatedTrait;

    protected HangupAllCallsView $view;

    public function __construct()
    {
        $this->view = new HangupAllCallsView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new HangupAllCallsResponse();
        $inquiry = HangupAllCallsInquiry::factory($request);

        return $this->doExecute($request, $response, $inquiry);
    }
}
