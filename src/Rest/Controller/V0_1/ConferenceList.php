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

use RTCKit\Eqivo\Rest\Inquiry\V0_1\ConferenceList as ConferenceListInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\ConferenceList as ConferenceListResponse;

use RTCKit\Eqivo\Rest\View\V0_1\ConferenceList as ConferenceListView;

/**
 * @OA\Post(
 *      tags={"Conference"},
 *      path="/v0.1/ConferenceList/",
 *      summary="/v0.1/ConferenceList/",
 *      description="Returns a list of all established conferences",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/ConferenceListParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/ConferenceListResponse"),
 *          ),
 *      ),
 * )
 */
class ConferenceList implements ControllerInterface
{
    use AuthenticatedTrait;

    protected ConferenceListView $view;

    public function __construct()
    {
        $this->view = new ConferenceListView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new ConferenceListResponse();
        $inquiry = ConferenceListInquiry::factory($request);

        return $this->doExecute($request, $response, $inquiry);
    }
}
