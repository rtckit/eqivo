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
use RTCKit\Eqivo\Rest\Inquiry\V0_1\Call as CallInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\Call as CallResponse;
use RTCKit\Eqivo\Rest\View\V0_1\Call as CallView;

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
 *      path="/v0.1/Call/",
 *      summary="/v0.1/Call/",
 *      description="Initiates an outbound call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/CallParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/CallResponse"),
 *          ),
 *      ),
 * )
 */
class Call implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;
    use DialerTrait;

    protected CallView $view;

    public function __construct()
    {
        $this->view = new CallView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = CallInquiry::factory($request);
                $response = new CallResponse;
                $response->RestApiServer = $this->app->config->restServerAdvertisedHost;

                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                $response->Message = CallResponse::MESSAGE_SUCCESS;
                $response->RequestUUID = Uuid::uuid4()->toString();
                $response->Success = true;

                $this->perform($inquiry, $response);

                return resolve($this->view->execute($response));
            })
            ->otherwise([$this, 'exceptionHandler']);
    }

    /**
     * Validates API call parameters
     *
     * @param CallInquiry $inquiry
     * @param CallResponse $response
     */
    public function validate(CallInquiry $inquiry, CallResponse $response): void
    {
        if (isset($inquiry->CoreUUID)) {
            $core = $this->app->getCore($inquiry->CoreUUID);

            if (is_null($core)) {
                $response->Message = CallResponse::MESSAGE_UNKNOWN_CORE;
                $response->RequestUUID = '';
                $response->Success = false;

                return;
            }

            $inquiry->core = $core;
        } else {
            try {
                $inquiry->core = $this->app->allocateCore();
            } catch (CoreException $e) {
                $response->Message = $e->getMessage();
                $response->RequestUUID = '';
                $response->Success = false;

                return;
            }
        }

        /*
         * The legacy implementation mentions more required parameters:
         *   https://github.com/rtckit/plivoframework/blob/29fc41fb3c887d5d9022a941e87bbeb2269112ff/src/plivo/rest/freeswitch/api.py#L486-L502
         *
         * however, only the following are enforced:
         *   https://github.com/rtckit/plivoframework/blob/29fc41fb3c887d5d9022a941e87bbeb2269112ff/src/plivo/rest/freeswitch/api.py#L551
         */
        if (
            !isset($inquiry->From) ||
            !isset($inquiry->To) ||
            !isset($inquiry->Gateways) ||
            !isset($inquiry->AnswerUrl)
        ) {
            $response->Message = CallResponse::MESSAGE_MANDATORY_MISSING;
            $response->RequestUUID = '';
            $response->Success = false;

            return;
        }

        if (!filter_var($inquiry->AnswerUrl, FILTER_VALIDATE_URL)) {
            $response->Message = CallResponse::MESSAGE_ANSWERURL_INVALID;
            $response->RequestUUID = '';
            $response->Success = false;

            return;
        }

        if (empty($inquiry->HangupUrl)) {
            $inquiry->HangupUrl = $inquiry->AnswerUrl;
        } else if (!filter_var($inquiry->HangupUrl, FILTER_VALIDATE_URL)) {
            $response->Message = CallResponse::MESSAGE_HANGUPURL_INVALID;
            $response->RequestUUID = '';
            $response->Success = false;

            return;
        }

        if (!empty($inquiry->RingUrl) && !filter_var($inquiry->RingUrl, FILTER_VALIDATE_URL)) {
            $response->Message = CallResponse::MESSAGE_RINGURL_INVALID;
            $response->RequestUUID = '';
            $response->Success = false;

            return;
        }
    }


    /**
     * Performs the API call function
     *
     * @param CallInquiry $inquiry
     * @param CallResponse $response
     */
    public function perform(CallInquiry $inquiry, CallResponse $response): void
    {
        $ringUrl = $inquiry->RingUrl ?? '';

        $callRequest = new CallRequest;
        $callRequest->uuid = $response->RequestUUID;
        $callRequest->to = $inquiry->To;
        $callRequest->from = $inquiry->From;
        $callRequest->ringUrl = $ringUrl;
        $callRequest->hangupUrl = $inquiry->HangupUrl;

        if (isset($inquiry->AccountSID)) {
            $callRequest->accountSid = $inquiry->AccountSID;
        }

        $inquiry->core->addCallRequest($callRequest);

        $gateways = explode(',', $inquiry->Gateways);
        $callRequest->gateways = [];
        $gatewayCodecs = !empty($inquiry->GatewayCodecs)
            ? str_getcsv($inquiry->GatewayCodecs, ',', "'")
            : [];
        $gatewayTimeouts = !empty($inquiry->GatewayTimeouts) ? explode(',', $inquiry->GatewayTimeouts) : [];
        $gatewayRetries = !empty($inquiry->GatewayRetries) ? explode(',', $inquiry->GatewayRetries) : [];

        $vars = [
            "{$this->app->config->appPrefix}_request_uuid={$response->RequestUUID}",
            "{$this->app->config->appPrefix}_answer_url={$inquiry->AnswerUrl}",
            "{$this->app->config->appPrefix}_ring_url={$ringUrl}",
            "{$this->app->config->appPrefix}_hangup_url={$inquiry->HangupUrl}",
            "origination_caller_id_number={$inquiry->From}",
        ];

        if (isset($inquiry->CallerName)) {
            $vars[] = "origination_caller_id_name='". str_replace("'", "\\'", $inquiry->CallerName) . "'";
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

        if (isset($inquiry->HangupOnRing)) {
            $hupOnRing = (int)$inquiry->HangupOnRing;
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

        $sendOnPreanswer = !empty($inquiry->SendOnPreanswer) && ($inquiry->SendOnPreanswer === 'true');

        if (isset($inquiry->SendDigits)) {
            if ($sendOnPreanswer) {
                $vars[] = "execute_on_media_{$execOnMedia}='send_dtmf {$inquiry->SendDigits}'";
                $execOnMedia++;
            } else {
                $vars[] = "execute_on_answer='send_dtmf {$inquiry->SendDigits}'";
            }
        }

        $timeLimit = -1;

        if (isset($inquiry->TimeLimit)) {
            $timeLimit = (int)$inquiry->TimeLimit;
        }

        if ($timeLimit > 0) {
            $schedHup = new ScheduledHangup;
            $schedHup->uuid = Uuid::uuid4()->toString();
            $schedHup->timeout = $timeLimit;

            $inquiry->core->addScheduledHangup($schedHup);

            $vars[] = "api_on_answer_1='sched_api +{$timeLimit} {$schedHup->uuid} hupall " .
                HangupCauseEnum::ALLOTTED_TIMEOUT->value .
                " {$this->app->config->appPrefix}_request_uuid {$response->RequestUUID}'";
            $vars[] = "{$this->app->config->appPrefix}_sched_hangup_id={$schedHup->uuid}";
        }

        $vars[] = "{$this->app->config->appPrefix}_from='{$inquiry->From}'";
        $vars[] = "{$this->app->config->appPrefix}_to='{$inquiry->To}'";

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
