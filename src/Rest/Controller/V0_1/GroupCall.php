<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};

use function React\Promise\resolve;
use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\Eqivo\Exception\{
    RestXmlFormatException,
    RestXmlSyntaxException
};
use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
};
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\Eqivo\Rest\Inquiry\V0_1\GroupCall as GroupCallInquiry;

use RTCKit\Eqivo\Rest\Response\AbstractResponse;
use RTCKit\Eqivo\Rest\Response\V0_1\GroupCall as GroupCallResponse;
use RTCKit\Eqivo\Rest\View\V0_1\GroupCall as GroupCallView;
use RTCKit\FiCore\Plan\AbstractElement;
use RTCKit\FiCore\Plan\Playback\Element as PlaybackElement;
use RTCKit\FiCore\Plan\Silence\Element as SilenceElement;
use RTCKit\FiCore\Plan\Speak\Element as SpeakElement;

use RTCKit\FiCore\Switch\{
    AbstractApp,
    Channel,
    HangupCauseEnum,
    Job,
    OriginateJob,
    ScheduledHangup
};

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

    public const DISALLOWED_DELIMITERS = [',', '/'];

    public const DEFAULT_REJECT_REASONS = 'NO_ANSWER ORIGINATOR_CANCEL ALLOTTED_TIMEOUT NO_USER_RESPONSE CALL_REJECTED';

    protected GroupCallView $view;

    public function __construct()
    {
        $this->view = new GroupCallView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new GroupCallResponse();
        $inquiry = GroupCallInquiry::factory($request);

        $promise = resolve();

        if (isset($inquiry->ConfirmSound) && filter_var($inquiry->ConfirmSound, FILTER_VALIDATE_URL)) {
            /* Owning to the legacy's framework model, we're instantiating a pseudo Channel to fetch the ConfirmSound RestXML */
            $channel = new Channel();
            $channel->app = $this->app;

            $promise = $this->app->planProducer->produce($channel, "{$this->app->restServer->config->defaultHttpMethod}:{$inquiry->ConfirmSound}")
                ->then(function (array $elements) use ($channel, $inquiry): PromiseInterface {
                    $soundFiles = [];

                    if (count($elements)) {
                        /** @var list<AbstractElement> $elements */
                        $soundFiles = $this->app->planProducer->buildPlaybackArray(
                            $channel,
                            $elements,
                            [PlaybackElement::class, SilenceElement::class, SpeakElement::class]
                        );

                        if (isset($channel->ttsEngine, $channel->ttsVoice)) {
                            if (isset($inquiry->ExtraDialString)) {
                                $inquiry->ExtraDialString .= ",";
                            } else {
                                $inquiry->ExtraDialString = "";
                            }

                            $inquiry->ExtraDialString .= "tts_engine={$channel->ttsEngine},tts_voice={$channel->ttsVoice}";
                        }
                    }

                    $inquiry->confirmMedia = $soundFiles;

                    return resolve();
                });
        }

        return $promise->then(function () use ($request, $response, $inquiry) {
            return $this->doExecute($request, $response, $inquiry);
        });
    }

    /**
     * Validates API call parameters
     *
     * @param AbstractInquiry $inquiry
     * @param AbstractResponse $response
     */
    public function validate(AbstractInquiry $inquiry, AbstractResponse $response): void
    {
        assert($inquiry instanceof GroupCallInquiry);
        assert($response instanceof GroupCallResponse);

        if (isset($inquiry->CoreUUID)) {
            $core = $this->app->getCore($inquiry->CoreUUID);

            if (!isset($core)) {
                $response->Message = GroupCallResponse::MESSAGE_UNKNOWN_CORE;
                $response->RequestUUID = '';
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
        } elseif (!filter_var($inquiry->HangupUrl, FILTER_VALIDATE_URL)) {
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

        $inquiry->defaultHttpMethod = $this->app->restServer->config->defaultHttpMethod;
    }
}
