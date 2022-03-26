<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\{
    App,
    CallRequest,
    HangupCauseEnum,
    Job,
    ScheduledHangup
};
use RTCKit\Eqivo\Exception\CoreException;
use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    DialerTrait,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\BulkCall as BulkCallInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\BulkCall as BulkCallResponse;
use RTCKit\Eqivo\Rest\View\V0_1\BulkCall as BulkCallView;

use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\ESL;
use function React\Promise\resolve;

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
    use ErrorableTrait;
    use DialerTrait;

    public const DISALLOWED_DELIMITERS = [',', '/'];

    protected BulkCallView $view;

    public function __construct()
    {
        $this->view = new BulkCallView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = BulkCallInquiry::factory($request);
                $response = new BulkCallResponse;
                $response->RestApiServer = $this->app->config->restServerAdvertisedHost;

                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                $response->Message = BulkCallResponse::MESSAGE_SUCCESS;
                $response->Success = true;

                $this->perform($inquiry, $response);

                return resolve($this->view->execute($response));
            })
            ->otherwise([$this, 'exceptionHandler']);
    }

    /**
     * Validates API call parameters
     *
     * @param BulkCallInquiry $inquiry
     * @param BulkCallResponse $response
     */
    public function validate(BulkCallInquiry $inquiry, BulkCallResponse $response): void
    {
        if (isset($inquiry->CoreUUID)) {
            $core = $this->app->getCore($inquiry->CoreUUID);

            if (is_null($core)) {
                $response->Message = BulkCallResponse::MESSAGE_UNKNOWN_CORE;
                $response->Success = false;

                return;
            }

            $inquiry->core = $core;
        } else {
            try {
                $inquiry->core = $this->app->allocateCore();
            } catch (CoreException $e) {
                $response->Message = $e->getMessage();
                $response->Success = false;

                return;
            }
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
        } else if (!filter_var($inquiry->HangupUrl, FILTER_VALIDATE_URL)) {
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
    }

    /**
     * Performs the API call function
     *
     * @param BulkCallInquiry $inquiry
     * @param BulkCallResponse $response
     */
    public function perform(BulkCallInquiry $inquiry, BulkCallResponse $response): void
    {
        $ringUrl = $inquiry->RingUrl ?? '';

        foreach ($inquiry->toList as $idx => $to) {
            $callRequest = new CallRequest;
            $callRequest->uuid = Uuid::uuid4()->toString();
            $callRequest->to = $to;
            $callRequest->from = $inquiry->From;
            $callRequest->ringUrl = $ringUrl;
            $callRequest->hangupUrl = $inquiry->HangupUrl;

            $response->RequestUUID[] = $callRequest->uuid;

            if (isset($inquiry->AccountSID)) {
                $callRequest->accountSid = $inquiry->AccountSID;
            }

            $inquiry->core->addCallRequest($callRequest);

            $gateways = explode(',', $inquiry->gwList[$idx]);
            $callRequest->gateways = [];
            $gatewayCodecs = !empty($inquiry->gwCodecsList[$idx])
                ? str_getcsv($inquiry->gwCodecsList[$idx], ',', "'")
                : [];
            $gatewayTimeouts = !empty($inquiry->gwTimeoutsList[$idx]) ? explode(',', $inquiry->gwTimeoutsList[$idx]) : [];
            $gatewayRetries = !empty($inquiry->gwRetriesList[$idx]) ? explode(',', $inquiry->gwRetriesList[$idx]) : [];

            $vars = [
                "{$this->app->config->appPrefix}_request_uuid={$callRequest->uuid}",
                "{$this->app->config->appPrefix}_answer_url={$inquiry->AnswerUrl}",
                "{$this->app->config->appPrefix}_ring_url={$ringUrl}",
                "{$this->app->config->appPrefix}_hangup_url={$inquiry->HangupUrl}",
                "origination_caller_id_number={$inquiry->From}",
            ];

            if (isset($inquiry->callerNameList[$idx])) {
                $vars[] = "origination_caller_id_name='". str_replace("'", "\\'", $inquiry->callerNameList[$idx]) . "'";
            }

            if (!empty($inquiry->ExtraDialString)) {
                $extra = str_getcsv($inquiry->ExtraDialString, ',', "'");

                foreach ($extra as $var) {
                    $vars[] = $var;
                }
            }

            if (!empty($inquiry->AccountSID)) {
                $vars[] = "{$this->app->config->appPrefix}_accountsid={$inquiry->AccountSID}";
            }

            $hupOnRing = -1;
            $execOnMedia = 1;

            if (isset($inquiry->hupOnRingList[$idx])) {
                $hupOnRing = (int)$inquiry->hupOnRingList[$idx];
            }

            if (!$hupOnRing) {
                $vars[] = "execute_on_media='hangup ORIGINATOR_CANCEL'";
                $vars[] = "execute_on_ring='hangup ORIGINATOR_CANCEL'";
                $execOnMedia++;
            } else if ($hupOnRing > 1) {
                $vars[] = "execute_on_media_{$execOnMedia}='sched_hangup +{$hupOnRing} ORIGINATOR_CANCEL'";
                $vars[] = "execute_on_ring='sched_hangup +{$hupOnRing} ORIGINATOR_CANCEL'";
                $execOnMedia++;
            }

            $sendOnPreanswer = !empty($inquiry->sendPreanswerList[$idx]) && ($inquiry->sendPreanswerList[$idx] === 'true');

            if (isset($inquiry->sendDigitsList[$idx])) {
                if ($sendOnPreanswer) {
                    $vars[] = "execute_on_media_{$execOnMedia}='send_dtmf {$inquiry->sendDigitsList[$idx]}'";
                    $execOnMedia++;
                } else {
                    $vars[] = "execute_on_answer='send_dtmf {$inquiry->sendDigitsList[$idx]}'";
                }
            }

            $timeLimit = -1;

            if (isset($inquiry->timeLimitList[$idx])) {
                $timeLimit = (int)$inquiry->timeLimitList[$idx];
            }

            if ($timeLimit > 0) {
                $schedHup = new ScheduledHangup;
                $schedHup->uuid = Uuid::uuid4()->toString();
                $schedHup->timeout = $timeLimit;

                $inquiry->core->addScheduledHangup($schedHup);

                $vars[] = "api_on_answer_1='sched_api +{$timeLimit} {$schedHup->uuid} hupall " .
                    HangupCauseEnum::ALLOTTED_TIMEOUT->value .
                    " {$this->app->config->appPrefix}_request_uuid {$callRequest->uuid}'";
                $vars[] = "{$this->app->config->appPrefix}_sched_hangup_id={$schedHup->uuid}";
            }

            $vars[] = "{$this->app->config->appPrefix}_from='{$inquiry->From}'";
            $vars[] = "{$this->app->config->appPrefix}_to='{$to}'";

            foreach ($gateways as $gwIdx => $gateway) {
                $gwVars = $vars;

                $gwVars[] = "{$this->app->config->appPrefix}_app=true";

                if (!empty($gatewayCodecs[$gwIdx])) {
                    $gwVars[] = "absolute_codec_string='" . $gatewayCodecs[$gwIdx] . "'";
                }

                if (!empty($gatewayTimeouts[$gwIdx])) {
                    $gwVars[] = 'originate_timeout=' . $gatewayTimeouts[$gwIdx];
                }

                $gwVars[] = 'ignore_early_media=true';

                $endpoint = $gateway . $inquiry->To;
                $retries = empty($gatewayRetries[$gwIdx]) ? 1 : (int)$gatewayRetries[$gwIdx];

                for ($i = 0; $i < $retries; $i++) {
                    $callRequest->gateways[] = 'originate {' . implode(',', $gwVars) . '}' . $endpoint .
                        " &socket('{$this->app->config->outboundServerAdvertisedIp}:{$this->app->config->outboundServerAdvertisedPort} async full')";
                }
            }

            $this->loopGateways($callRequest);
        }
    }
}
