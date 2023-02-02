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
use RTCKit\Eqivo\Rest\Inquiry\V0_1\ConferenceKick as ConferenceKickInquiry;

use RTCKit\Eqivo\Rest\Response\AbstractResponse;
use RTCKit\Eqivo\Rest\Response\V0_1\ConferenceKick as ConferenceKickResponse;

use RTCKit\Eqivo\Rest\View\V0_1\ConferenceKick as ConferenceKickView;

/**
 * @OA\Post(
 *      tags={"Conference"},
 *      path="/v0.1/ConferenceKick/",
 *      summary="/v0.1/ConferenceKick/",
 *      description="Kicks one or more conference members",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/ConferenceKickParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/ConferenceKickResponse"),
 *          ),
 *      ),
 * )
 */
class ConferenceKick implements ControllerInterface
{
    use AuthenticatedTrait;

    protected ConferenceKickView $view;

    public function __construct()
    {
        $this->view = new ConferenceKickView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new ConferenceKickResponse();
        $inquiry = ConferenceKickInquiry::factory($request);

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
        assert($inquiry instanceof ConferenceKickInquiry);
        assert($response instanceof ConferenceKickResponse);

        if (!isset($inquiry->ConferenceName)) {
            $response->Message = ConferenceKickResponse::MESSAGE_NO_CONFERENCE_NAME;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->MemberID)) {
            $response->Message = ConferenceKickResponse::MESSAGE_NO_MEMBER_ID;
            $response->Success = false;

            return;
        }

        $conference = $this->app->getConferenceByRoom($inquiry->ConferenceName);

        if (!isset($conference)) {
            $response->Message = ConferenceKickResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->core = $conference->core;
    }
}
