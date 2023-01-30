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
use RTCKit\Eqivo\Rest\Inquiry\V0_1\TransferCall as TransferCallInquiry;

use RTCKit\Eqivo\Rest\Response\AbstractResponse;
use RTCKit\Eqivo\Rest\Response\V0_1\TransferCall as TransferCallResponse;

use RTCKit\Eqivo\Rest\View\V0_1\TransferCall as TransferCallView;

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/TransferCall/",
 *      summary="/v0.1/TransferCall/",
 *      description="Replaces the RestXML flow of a live call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/TransferCallParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/TransferCallResponse"),
 *          ),
 *      ),
 * )
 */
class TransferCall implements ControllerInterface
{
    use AuthenticatedTrait;

    protected TransferCallView $view;

    public function __construct()
    {
        $this->view = new TransferCallView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new TransferCallResponse();
        $inquiry = TransferCallInquiry::factory($request);

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
        assert($inquiry instanceof TransferCallInquiry);
        assert($response instanceof TransferCallResponse);

        if (!isset($inquiry->CallUUID)) {
            $response->Message = TransferCallResponse::MESSAGE_NO_CALLUUID;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->Url)) {
            $response->Message = TransferCallResponse::MESSAGE_NO_URL;
            $response->Success = false;

            return;
        }

        if (!(filter_var($inquiry->Url, FILTER_VALIDATE_URL))) {
            $response->Message = TransferCallResponse::MESSAGE_INVALID_URL;
            $response->Success = false;

            return;
        }

        $channel = $this->app->getChannel($inquiry->CallUUID);

        if (!isset($channel)) {
            $response->Message = TransferCallResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->channel = $channel;
        $inquiry->defaultHttpMethod = $this->app->restServer->config->defaultHttpMethod;
    }
}
