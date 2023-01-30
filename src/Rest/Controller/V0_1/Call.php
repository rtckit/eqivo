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
    DialerTrait,
};
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\Eqivo\Rest\Inquiry\V0_1\Call as CallInquiry;
use RTCKit\Eqivo\Rest\Response\AbstractResponse;
use RTCKit\Eqivo\Rest\Response\V0_1\Call as CallResponse;

use RTCKit\Eqivo\Rest\View\V0_1\Call as CallView;
use RTCKit\Eqivo\{
    AbstractApp,
    HangupCauseEnum,
    Job,
    OriginateJob,
    ScheduledHangup
};

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

    /** @var string */
    public const AMD_ENABLE = 'Enable';

    /** @var string */
    public const AMD_MSG_END = 'DetectMessageEnd';

    /** @var string */
    public const DEFAULT_AMD_METHOD = 'POST';

    /** @var int */
    public const DEFAULT_AMD_TIMEOUT = 30;

    /** @var int */
    public const DEFAULT_AMD_SPEECH_THRESHOLD = 2400;

    /** @var int */
    public const DEFAULT_AMD_SILENCE_THRESHOLD = 1200;

    /** @var int */
    public const DEFAULT_AMD_INITIAL_SILENCE = 5000;

    protected CallView $view;

    public function __construct()
    {
        $this->view = new CallView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new CallResponse();
        $inquiry = CallInquiry::factory($request);

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
        assert($inquiry instanceof CallInquiry);
        assert($response instanceof CallResponse);

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
        } elseif (!filter_var($inquiry->HangupUrl, FILTER_VALIDATE_URL)) {
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

        if (isset($inquiry->CoreUUID)) {
            $core = $this->app->getCore($inquiry->CoreUUID);

            if (!isset($core)) {
                $response->Message = CallResponse::MESSAGE_UNKNOWN_CORE;
                $response->RequestUUID = '';
                $response->Success = false;

                return;
            }

            $inquiry->core = $core;
        }

        /* The legacy framework did not feature Answering Machine Detection; FiCore's implementation employs a Twilioesque fashion:
         *   https://web.archive.org/web/20220324023024/https://www.twilio.com/docs/voice/answering-machine-detection
         */
        if (
            isset($inquiry->MachineDetection) &&
            !in_array($inquiry->MachineDetection, [static::AMD_ENABLE, static::AMD_MSG_END])
        ) {
            $response->Message = CallResponse::MESSAGE_INVALID_AMD;
            $response->Success = false;

            return;
        }

        /* Per https://web.archive.org/web/20220405231029/https://www.twilio.com/docs/voice/api/call-resource if both SendDigits
         * and MachineDetection parameters are provided, then MachineDetection will be ignored.
         */
        if (isset($inquiry->SendDigits)) {
            unset($inquiry->MachineDetection);
        }

        if (isset($inquiry->MachineDetection)) {
            $inquiry->AsyncAMD = isset($inquiry->AsyncAMD) ? ($inquiry->AsyncAMD === 'true') : false;

            if (isset($inquiry->AsyncAmdStatusCallbackMethod)) {
                if (!in_array($inquiry->AsyncAmdStatusCallbackMethod, ['GET', 'POST'])) {
                    $response->Message = CallResponse::MESSAGE_INVALID_AMD_METHOD;
                    $response->Success = false;

                    return;
                }
            } else {
                $inquiry->AsyncAmdStatusCallbackMethod = static::DEFAULT_AMD_METHOD;
            }

            if (isset($inquiry->AsyncAmdStatusCallback) && !filter_var($inquiry->AsyncAmdStatusCallback, FILTER_VALIDATE_URL)) {
                $response->Message = CallResponse::MESSAGE_AMD_URL_INVALID;
                $response->Success = false;

                return;
            }

            if (isset($inquiry->MachineDetectionTimeout)) {
                if (!is_numeric($inquiry->MachineDetectionTimeout)) {
                    $response->Message = CallResponse::MESSAGE_AMD_TIMEOUT_NOT_INT;
                    $response->Success = false;

                    return;
                }

                $inquiry->MachineDetectionTimeout = (int)$inquiry->MachineDetectionTimeout;

                if (($inquiry->MachineDetectionTimeout < 3) || ($inquiry->MachineDetectionTimeout > 59)) {
                    $response->Message = CallResponse::MESSAGE_AMD_TIMEOUT_BAD_RANGE;
                    $response->Success = false;

                    return;
                }
            } else {
                $inquiry->MachineDetectionTimeout = static::DEFAULT_AMD_TIMEOUT;
            }

            if (isset($inquiry->MachineDetectionSpeechThreshold)) {
                if (!is_numeric($inquiry->MachineDetectionSpeechThreshold)) {
                    $response->Message = CallResponse::MESSAGE_AMD_SPEECH_THRESHOLD_NOT_INT;
                    $response->Success = false;

                    return;
                }

                $inquiry->MachineDetectionSpeechThreshold = (int)$inquiry->MachineDetectionSpeechThreshold;

                if (($inquiry->MachineDetectionSpeechThreshold < 1000) || ($inquiry->MachineDetectionSpeechThreshold > 6000)) {
                    $response->Message = CallResponse::MESSAGE_AMD_SPEECH_THRESHOLD_BAD_RANGE;
                    $response->Success = false;

                    return;
                }
            } else {
                $inquiry->MachineDetectionSpeechThreshold = static::DEFAULT_AMD_SPEECH_THRESHOLD;
            }

            if (isset($inquiry->MachineDetectionSpeechEndThreshold)) {
                if (!is_numeric($inquiry->MachineDetectionSpeechEndThreshold)) {
                    $response->Message = CallResponse::MESSAGE_AMD_SILENCE_THRESHOLD_NOT_INT;
                    $response->Success = false;

                    return;
                }

                $inquiry->MachineDetectionSpeechEndThreshold = (int)$inquiry->MachineDetectionSpeechEndThreshold;

                if (($inquiry->MachineDetectionSpeechEndThreshold < 500) || ($inquiry->MachineDetectionSpeechEndThreshold > 5000)) {
                    $response->Message = CallResponse::MESSAGE_AMD_SILENCE_THRESHOLD_BAD_RANGE;
                    $response->Success = false;

                    return;
                }
            } else {
                $inquiry->MachineDetectionSpeechEndThreshold = static::DEFAULT_AMD_SILENCE_THRESHOLD;
            }

            if (isset($inquiry->MachineDetectionSilenceTimeout)) {
                if (!is_numeric($inquiry->MachineDetectionSilenceTimeout)) {
                    $response->Message = CallResponse::MESSAGE_AMD_INITIAL_SILENCE_NOT_INT;
                    $response->Success = false;

                    return;
                }

                $inquiry->MachineDetectionSilenceTimeout = (int)$inquiry->MachineDetectionSilenceTimeout;

                if (($inquiry->MachineDetectionSilenceTimeout < 2000) || ($inquiry->MachineDetectionSilenceTimeout > 10000)) {
                    $response->Message = CallResponse::MESSAGE_AMD_INITIAL_SILENCE_BAD_RANGE;
                    $response->Success = false;

                    return;
                }
            } else {
                $inquiry->MachineDetectionSilenceTimeout = static::DEFAULT_AMD_INITIAL_SILENCE;
            }
        }

        $inquiry->defaultHttpMethod = $this->app->restServer->config->defaultHttpMethod;
    }
}
