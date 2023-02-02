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
use RTCKit\Eqivo\Rest\Inquiry\V0_1\SendDigits as SendDigitsInquiry;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;
use RTCKit\Eqivo\Rest\Response\V0_1\SendDigits as SendDigitsResponse;

use RTCKit\Eqivo\Rest\View\V0_1\SendDigits as SendDigitsView;
use RTCKit\Eqivo\{
    AbstractApp,
    HangupCauseEnum
};

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/SendDigits/",
 *      summary="/v0.1/SendDigits/",
 *      description="Emits DMTF tones to a call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/SendDigitsParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/SendDigitsResponse"),
 *          ),
 *      ),
 * )
 */
class SendDigits implements ControllerInterface
{
    use AuthenticatedTrait;

    public const DEFAULT_LEG = 'aleg';

    protected SendDigitsView $view;

    public function __construct()
    {
        $this->view = new SendDigitsView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new SendDigitsResponse();
        $inquiry = SendDigitsInquiry::factory($request);

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
        assert($inquiry instanceof SendDigitsInquiry);
        assert($response instanceof SendDigitsResponse);

        if (!isset($inquiry->CallUUID)) {
            $response->Message = SendDigitsResponse::MESSAGE_NO_CALLUUID;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->Digits)) {
            $response->Message = SendDigitsResponse::MESSAGE_NO_DIGITS;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->Leg)) {
            $inquiry->Leg = static::DEFAULT_LEG;
        } else {
            if (!in_array($inquiry->Leg, ['aleg', 'bleg'])) {
                $response->Message = SendDigitsResponse::MESSAGE_INVALID_LEG;
                $response->Success = false;

                return;
            }
        }

        $channel = $this->app->getChannel($inquiry->CallUUID);

        if (!isset($channel)) {
            $response->Message = SendDigitsResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->channel = $channel;
    }
}
