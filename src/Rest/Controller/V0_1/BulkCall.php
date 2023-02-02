<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
};
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\Eqivo\Rest\Inquiry\V0_1\BulkCall as BulkCallInquiry;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;
use RTCKit\Eqivo\Rest\Response\V0_1\BulkCall as BulkCallResponse;

use RTCKit\Eqivo\Rest\View\V0_1\BulkCall as BulkCallView;
use RTCKit\FiCore\Switch\{
    HangupCauseEnum,
    Job,
    OriginateJob,
    ScheduledHangup
};

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/BulkCall/",
 *      summary="/v0.1/BulkCall/",
 *      description="Initiates multiple concurrent outbound calls",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/BulkCallParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/BulkCallResponse"),
 *          ),
 *      ),
 * )
 */
class BulkCall implements ControllerInterface
{
    use AuthenticatedTrait;

    public const DISALLOWED_DELIMITERS = [',', '/'];

    protected BulkCallView $view;

    public function __construct()
    {
        $this->view = new BulkCallView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new BulkCallResponse();
        $inquiry = BulkCallInquiry::factory($request);

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
        assert($inquiry instanceof BulkCallInquiry);
        assert($response instanceof BulkCallResponse);

        if (isset($inquiry->CoreUUID)) {
            $core = $this->app->getCore($inquiry->CoreUUID);

            if (!isset($core)) {
                $response->Message = BulkCallResponse::MESSAGE_UNKNOWN_CORE;
                $response->RequestUUID = [];
                $response->Success = false;

                return;
            }

            $inquiry->core = $core;
        }

        if (
            !isset($inquiry->From) ||
            !isset($inquiry->To) ||
            !isset($inquiry->Gateways) ||
            !isset($inquiry->AnswerUrl) ||
            !isset($inquiry->Delimiter, $inquiry->Delimiter[0])
        ) {
            $response->Message = BulkCallResponse::MESSAGE_MANDATORY_MISSING;
            $response->Success = false;

            return;
        }

        if (in_array($inquiry->Delimiter, self::DISALLOWED_DELIMITERS)) {
            $response->Message = BulkCallResponse::MESSAGE_DELIMITER_DISALLOWED;
            $response->Success = false;

            return;
        }

        $separator = $inquiry->Delimiter;
        $inquiry->toList = explode($separator, $inquiry->To);

        if (!isset($inquiry->toList[1])) {
            $response->Message = BulkCallResponse::MESSAGE_INSUFFICIENT_NUMBERS;
            $response->Success = false;

            return;
        }

        $inquiry->gwList = explode($separator, $inquiry->Gateways);

        if (count($inquiry->toList) !== count($inquiry->gwList)) {
            $response->Message = BulkCallResponse::MESSAGE_MISMATCHED_PARAMETERS;
            $response->Success = false;

            return;
        }

        if (!filter_var($inquiry->AnswerUrl, FILTER_VALIDATE_URL)) {
            $response->Message = BulkCallResponse::MESSAGE_ANSWERURL_INVALID;
            $response->Success = false;

            return;
        }

        if (empty($inquiry->HangupUrl)) {
            $inquiry->HangupUrl = $inquiry->AnswerUrl;
        } elseif (!filter_var($inquiry->HangupUrl, FILTER_VALIDATE_URL)) {
            $response->Message = BulkCallResponse::MESSAGE_HANGUPURL_INVALID;
            $response->Success = false;

            return;
        }

        if (!empty($inquiry->RingUrl) && !filter_var($inquiry->RingUrl, FILTER_VALIDATE_URL)) {
            $response->Message = BulkCallResponse::MESSAGE_RINGURL_INVALID;
            $response->Success = false;

            return;
        }

        if (isset($inquiry->GatewayCodecs)) {
            $inquiry->gwCodecsList = explode($separator, $inquiry->GatewayCodecs);
        }

        if (isset($inquiry->GatewayTimeouts)) {
            $inquiry->gwTimeoutsList = explode($separator, $inquiry->GatewayTimeouts);
        }

        if (isset($inquiry->GatewayRetries)) {
            $inquiry->gwRetriesList = explode($separator, $inquiry->GatewayRetries);
        }

        if (isset($inquiry->SendDigits)) {
            $inquiry->sendDigitsList = explode($separator, $inquiry->SendDigits);
        }

        if (isset($inquiry->SendOnPreanswer)) {
            $inquiry->sendPreanswerList = explode($separator, $inquiry->SendOnPreanswer);
        }

        if (isset($inquiry->TimeLimit)) {
            $inquiry->timeLimitList = explode($separator, $inquiry->TimeLimit);
        }

        if (isset($inquiry->HangupOnRing)) {
            $inquiry->hupOnRingList = explode($separator, $inquiry->HangupOnRing);
        }

        if (isset($inquiry->CallerName)) {
            $inquiry->callerNameList = explode($separator, $inquiry->CallerName);
        }

        $inquiry->defaultHttpMethod = $this->app->restServer->config->defaultHttpMethod;
    }
}
