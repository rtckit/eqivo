<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\{
    App,
    CallRequest,
    HangupCauseEnum,
    Job,
    ScheduledHangup,
    Session
};
use RTCKit\Eqivo\Exception\{
    CoreException,
    RestXmlFormatException,
    RestXmlSyntaxException
};
use RTCKit\Eqivo\Outbound\{
    Play,
    RestXmlElement,
    Speak,
    Wait
};
use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    DialerTrait,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\GroupCall as GroupCallInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\GroupCall as GroupCallResponse;
use RTCKit\Eqivo\Rest\View\V0_1\GroupCall as GroupCallView;

use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};
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
 *      path="/v0.1/GroupCall/",
 *      summary="/v0.1/GroupCall/",
 *      description="Initiate multiple racing outbound calls",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/GroupCallParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/GroupCallResponse"),
 *          ),
 *      ),
 * )
 */
class GroupCall implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;

    public const DISALLOWED_DELIMITERS = [',', '/'];

    public const DEFAULT_REJECT_REASONS = 'NO_ANSWER ORIGINATOR_CANCEL ALLOTTED_TIMEOUT NO_USER_RESPONSE CALL_REJECTED';

    protected GroupCallView $view;

    public function __construct()
    {
        $this->view = new GroupCallView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = GroupCallInquiry::factory($request);
                $response = new GroupCallResponse;
                $response->RestApiServer = $this->app->config->restServerAdvertisedHost;

                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function () use ($response) {
                        $response->Message ??= GroupCallResponse::MESSAGE_SUCCESS;
                        $response->Success ??= true;

                        return resolve($this->view->execute($response));
                    });
            })
            ->otherwise([$this, 'exceptionHandler']);
    }

    /**
     * Validates API call parameters
     *
     * @param GroupCallInquiry $inquiry
     * @param GroupCallResponse $response
     */
    public function validate(GroupCallInquiry $inquiry, GroupCallResponse $response): void
    {
        if (isset($inquiry->CoreUUID)) {
            $core = $this->app->getCore($inquiry->CoreUUID);

            if (is_null($core)) {
                $response->Message = GroupCallResponse::MESSAGE_UNKNOWN_CORE;
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
            $response->Message = GroupCallResponse::MESSAGE_MANDATORY_MISSING;
            $response->Success = false;

            return;
        }

        if (in_array($inquiry->Delimiter, self::DISALLOWED_DELIMITERS)) {
            $response->Message = GroupCallResponse::MESSAGE_DELIMITER_DISALLOWED;
            $response->Success = false;

            return;
        }

        $separator = $inquiry->Delimiter;
        $inquiry->toList = explode($separator, $inquiry->To);

        if (!isset($inquiry->toList[1])) {
            $response->Message = GroupCallResponse::MESSAGE_INSUFFICIENT_NUMBERS;
            $response->Success = false;

            return;
        }

        $inquiry->gwList = explode($separator, $inquiry->Gateways);

        if (count($inquiry->toList) !== count($inquiry->gwList)) {
            $response->Message = GroupCallResponse::MESSAGE_MISMATCHED_PARAMETERS;
            $response->Success = false;

            return;
        }

        if (!filter_var($inquiry->AnswerUrl, FILTER_VALIDATE_URL)) {
            $response->Message = GroupCallResponse::MESSAGE_ANSWERURL_INVALID;
            $response->Success = false;

            return;
        }

        if (empty($inquiry->HangupUrl)) {
            $inquiry->HangupUrl = $inquiry->AnswerUrl;
        } else if (!filter_var($inquiry->HangupUrl, FILTER_VALIDATE_URL)) {
            $response->Message = GroupCallResponse::MESSAGE_HANGUPURL_INVALID;
            $response->Success = false;

            return;
        }

        if (!empty($inquiry->RingUrl) && !filter_var($inquiry->RingUrl, FILTER_VALIDATE_URL)) {
            $response->Message = GroupCallResponse::MESSAGE_RINGURL_INVALID;
            $response->Success = false;

            return;
        }

        if (isset($inquiry->RejectCauses)) {
            $inquiry->RejectCauses = str_replace(',', ' ', $inquiry->RejectCauses);
        } else {
            $inquiry->RejectCauses = self::DEFAULT_REJECT_REASONS;
        }

        if (isset($inquiry->ConfirmSound) && !filter_var($inquiry->ConfirmSound, FILTER_VALIDATE_URL)) {
            $response->Message = GroupCallResponse::MESSAGE_CONFIRMSOUND_INVALID;
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
     * @param GroupCallInquiry $inquiry
     * @param GroupCallResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(GroupCallInquiry $inquiry, GroupCallResponse $response): PromiseInterface
    {
        $callRequest = new CallRequest;

        return (isset($inquiry->ConfirmSound) ? $this->prepareConfirmSounds($inquiry->ConfirmSound) : resolve([]))
            ->then(function (array $confirmSounds) use ($response, $inquiry, $callRequest): PromiseInterface {
                $ringUrl = $inquiry->RingUrl ?? '';

                $callRequest->uuid = Uuid::uuid4()->toString();
                $callRequest->to = $inquiry->To;
                $callRequest->from = $inquiry->From;
                $callRequest->ringUrl = $ringUrl;
                $callRequest->hangupUrl = $inquiry->HangupUrl;

                $response->RequestUUID = $callRequest->uuid;

                if (isset($inquiry->AccountSID)) {
                    $callRequest->accountSid = $inquiry->AccountSID;
                }

                $inquiry->core->addCallRequest($callRequest);

                $globals = [
                    "{$this->app->config->appPrefix}_app=true",
                    "{$this->app->config->appPrefix}_group_call=true",
                    "{$this->app->config->appPrefix}_request_uuid={$callRequest->uuid}",
                    "{$this->app->config->appPrefix}_answer_url={$inquiry->AnswerUrl}",
                    "{$this->app->config->appPrefix}_ring_url={$ringUrl}",
                    "{$this->app->config->appPrefix}_hangup_url={$inquiry->HangupUrl}",
                    "origination_caller_id_number={$inquiry->From}",
                    'ignore_early_media=true',
                    "fail_on_single_reject='{$inquiry->RejectCauses}'",
                ];

                if (isset($confirmSounds[0])) {
                    $playStr = 'file_string://silence_stream://1!' . implode('!', $confirmSounds);

                    if (isset($inquiry->ConfirmKey)) {
                        $globals[] = 'group_confirm_file=' . $playStr;
                        $globals[] = 'group_confirm_key=' . $inquiry->ConfirmKey;
                    } else {
                        $globals[] = 'group_confirm_file=playback ' . $playStr;
                        $globals[] = 'group_confirm_key=exec';
                    }

                    $globals[] = 'group_confirm_cancel_timeout=1';
                    $globals[] = 'playback_delimiter=!';
                }

                $dialStrs = [];

                foreach ($inquiry->toList as $idx => $to) {
                    $gateways = explode(',', $inquiry->gwList[$idx]);
                    $callRequest->gateways = [];
                    $gatewayCodecs = !empty($inquiry->gwCodecsList[$idx])
                        ? str_getcsv($inquiry->gwCodecsList[$idx], ',', "'")
                        : [];
                    $gatewayTimeouts = !empty($inquiry->gwTimeoutsList[$idx]) ? explode(',', $inquiry->gwTimeoutsList[$idx]) : [];
                    $gatewayRetries = !empty($inquiry->gwRetriesList[$idx]) ? explode(',', $inquiry->gwRetriesList[$idx]) : [];

                    $vars = [];

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

                    $dialStr = [];

                    foreach ($gateways as $gwIdx => $gateway) {
                        $gwVars = [];

                        if (!empty($gatewayCodecs[$gwIdx])) {
                            $gwVars[] = "absolute_codec_string='" . $gatewayCodecs[$gwIdx] . "'";
                        }

                        if (!empty($gatewayTimeouts[$gwIdx])) {
                            $gwVars[] = 'originate_timeout=' . $gatewayTimeouts[$gwIdx];
                        }

                        $endpoint = $gateway . $to;
                        $retries = empty($gatewayRetries[$gwIdx]) ? 1 : (int)$gatewayRetries[$gwIdx];

                        for ($i = 0; $i < $retries; $i++) {
                            if (isset($gwVars[0])) {
                                $dialStr[] = '[' . implode(',', $gwVars) . ']' . $endpoint;
                            } else {
                                $dialStr[] = $endpoint;
                            }
                        }
                    }

                    $dialStrs[] = '{' . implode(',', $vars) . '}' . implode(',', $dialStr);
                }

                $command = 'originate <' . implode(',', $globals) . '>' .
                    implode(':_:', $dialStrs) .
                    " &socket('{$this->app->config->outboundServerAdvertisedIp}:{$this->app->config->outboundServerAdvertisedPort} async full')";

                $this->app->restServer->logger->debug("GroupCall: {$command}");

                return $inquiry->core->client->bgApi(
                    (new ESL\Request\BgApi())->setParameters($command)
                );
            })
            ->then(function (ESL\Response $eslResponse) use ($response, $callRequest): PromiseInterface {
                $uuid = null;

                if ($eslResponse instanceof ESL\Response\CommandReply) {
                    $uuid = $eslResponse->getHeader('job-uuid');
                }

                if (!isset($uuid)) {
                    $response->Message = GroupCallResponse::MESSAGE_FAILED;
                    $response->Success = false;

                    unset($response->RequestUUID);
                    $callRequest->core->removeCallRequest($callRequest->uuid);

                    $this->app->restServer->logger->error("GroupCall Failed 'bgapi originate' -- JobUUID not received");
                } else {
                    $job = new Job;
                    $job->uuid = $uuid;
                    $job->command = 'originate';
                    $job->group = true;
                    $job->callRequest = $callRequest;
                    $job->deferred = new Deferred;

                    $callRequest->core->addJob($job);

                    assert(!isset($callRequest->job));

                    $callRequest->job = $job;
                }

                return resolve();
            });
    }

    protected function prepareConfirmSounds(string $url): PromiseInterface
    {
        return $this->app->httpClient->makeRequest($url)
            ->then(function (ResponseInterface $response) use ($url): array {
                $this->app->restServer->logger->info("Fetching remote confirm sound from RestXML {$url}");

                $xmlStr = (string)$response->getBody();

                if (!strlen($xmlStr)) {
                    $this->app->restServer->logger->warning('No XML Response');

                    return [];
                }

                $restXml = simplexml_load_string($xmlStr, RestXmlElement::class);

                if ($restXml === false) {
                    throw new RestXmlSyntaxException('Invalid RESTXML Response Syntax: ' . $xmlStr);
                }

                assert($restXml instanceof RestXmlElement);

                if ($restXml->getName() !== 'Response') {
                    throw new RestXmlFormatException('No Response Tag Present');
                }

                return $this->app->outboundServer->controller->buildPlaybackArray(
                    new Session, $restXml,
                    [Play\Handler::ELEMENT_TYPE, Speak\Handler::ELEMENT_TYPE, Wait\Handler::ELEMENT_TYPE]
                );
            })
            ->otherwise(function (\Throwable $t) {
                $t = $t->getPrevious() ?: $t;

                $this->app->restServer->logger->error('ConfirmSounds failure: ' . $t->getMessage(), [
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ]);
            });
    }
}
