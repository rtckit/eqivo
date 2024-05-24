<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\FiCore\Command\Channel\Originate;
use RTCKit\FiCore\Command\RequestInterface;

use RTCKit\Eqivo\Rest\Controller\V0_1\Call as CallController;
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\FiCore\Switch\{
    Core,
    Gateway,
};

/**
 * @OA\Schema(
 *     schema="CallParameters",
 *     required={"From", "To", "Gateways", "AnswerUrl"},
 * )
 */
class Call extends AbstractInquiry
{
    /**
     * @OA\Property(
     *     description="Phone number to be used as Caller ID",
     *     example="15551234567",
     * )
     */
    public string $From;

    /**
     * @OA\Property(
     *     description="Phone number to be called",
     *     example="15557654321",
     * )
     */
    public string $To;

    /**
     * @OA\Property(
     *     description="Comma separated FreeSWITCH gateway strings. When multiple gateways are specified, they will be tried sequentially (failover)",
     *     example="user/,sofia/gateway/PSTNgateway1/,sofia/gateway/PSTNgateway2/",
     * )
     */
    public string $Gateways;

    /**
     * @OA\Property(
     *     description="Fully qualified URL which will provide the RestXML once the call connects",
     *     example="https://example.com/answer/",
     * )
     */
    public string $AnswerUrl;

    /**
     * @OA\Property(
     *     description="Caller Name to be set for the call",
     *     example="Test",
     * )
     */
    public string $CallerName;

    /**
     * @OA\Property(
     *     description="Fully qualified URL to which the call hangup notification will be POSTed. `HangupCause` is added to the usual call [call notification parameters](#/components/schemas/CallNotificationParameters)",
     *     example="https://example.com/hangup/",
     * )
     */
    public string $HangupUrl;

    /**
     * @OA\Property(
     *     description="Fully qualified URL to which the call ringing notification will be POSTed. `RequestUUID` and `CallUUID` is added to the usual [call notification parameters](#/components/schemas/CallNotificationParameters)",
     *     example="https://example.com/ring/",
     * )
     */
    public string $RingUrl;

    public string $AccountSID;

    /**
     * @OA\Property(
     *     description="Additional [channel variables](https://freeswitch.org/confluence/display/FREESWITCH/Channel+Variables) to be added to the originate FreeSWITCH API call.",
     *     example="bridge_early_media=true,hangup_after_bridge=true",
     * )
     */
    public string $ExtraDialString;

    /**
     * @OA\Property(
     *     description="List of codec(s) to be used for each gateway. Enclose codec groups in single quotes",
     *     example="'PCMA,PCMU','G729,PCMU','PCMA,G729'",
     * )
     */
    public string $GatewayCodecs;

    /**
     * @OA\Property(
     *     description="List of maximum timeout amounts (in seconds) for each gateway",
     *     example="10,30,20",
     * )
     */
    public string $GatewayTimeouts;

    /**
     * @OA\Property(
     *     description="List of maximum retry counts for each gateway",
     *     example="3,1,2",
     * )
     */
    public string $GatewayRetries;

    /**
     * @OA\Property(
     *     description="DTMF tones to be sent when the call is answered. Each occurrence of `w` implies a 0.5 seconds delay whereas `W` will apply a whole second delay. To alter the tone duration (by default, 2000ms), append `@` and the length in milliseconds at the end of the string",
     *     example="1w2w3W#*@1500",
     * )
     */
    public string $SendDigits;

    /**
     * @OA\Property(
     *     description="When set to `true`, DTMF tones will be sent as early media rather than when the call is answered",
     *     type="bool",
     *     example=false,
     * )
     */
    public string $SendOnPreanswer;

    /**
     * @OA\Property(
     *     description="Schedules the call's hangup at a given time offset (in seconds) after the call is answered",
     *     type="int",
     *     minimum=1,
     *     example=90,
     * )
     */
    public string $TimeLimit;

    /**
     * @OA\Property(
     *     description="Schedules the call's hangup at a given time offset (in seconds) after the destination starts ringing",
     *     type="int",
     *     minimum=1,
     *     example=90,
     * )
     */
    public string $HangupOnRing;

    /**
     * @OA\Property(
     *     description="Core UUID of the desired FreeSWITCH instance (an FiCore extension)",
     *     example="45521fc6-a4b3-42b6-96ad-9136256be216"
     * )
     */
    public string $CoreUUID;

    /**
     * @OA\Property(
     *     description="Enables answering machine detection; optionally, it waits until the greeting message has been played back (an FiCore extension)",
     *     example="Enable",
     *     enum={CallController::ENABLE, CallController::AMD_MSG_END},
     * )
     */
    public string $MachineDetection;

    /**
     * @OA\Property(
     *     description="When set to `true`, the call flow execution is blocked until answering machine detection is complete (an FiCore extension)",
     *     type="bool",
     *     default=false,
     *     example=true,
     * )
     */
    public string|bool $AsyncAMD;

    /**
     * @OA\Property(
     *     description="HTTP method to be used when answering machine detection is completed (an FiCore extension)",
     *     example="GET",
     *     enum={"POST", "GET"},
     *     default=CallController::DEFAULT_AMD_METHOD,
     * )
     */
    public string $AsyncAmdStatusCallbackMethod;

    /**
     * @OA\Property(
     *     description="Fully qualified URL to which the answering machine detection result will be sent. `AnsweredBy` and `MachineDetectionDuration` are appended to the usual [call notification parameters](#/components/schemas/CallNotificationParameters) (an FiCore extension)",
     * )
     */
    public string $AsyncAmdStatusCallback;

    /**
     * @OA\Property(
     *     description="Amount of time (in seconds) allotted for answering machine detection assessment (an FiCore extension)",
     *     type="int",
     *     minimum=3,
     *     maximum=59,
     *     example=5,
     *     default=CallController::DEFAULT_AMD_TIMEOUT,
     * )
     */
    public string|int $MachineDetectionTimeout;

    /**
     * @OA\Property(
     *     description="Speech activity/utterance threshold (in milliseconds, an FiCore extension)",
     *     type="int",
     *     minimum=1000,
     *     maximum=6000,
     *     example=2000,
     *     default=CallController::DEFAULT_AMD_SPEECH_THRESHOLD,
     * )
     */
    public string|int $MachineDetectionSpeechThreshold;

    /**
     * @OA\Property(
     *     description="Silence threshold (in milliseconds, an FiCore extension)",
     *     type="int",
     *     minimum=500,
     *     maximum=5000,
     *     example=1000,
     *     default=CallController::DEFAULT_AMD_SILENCE_THRESHOLD,
     * )
     */
    public string|int $MachineDetectionSpeechEndThreshold;

    /**
     * @OA\Property(
     *     description="Initial silence threshold (in milliseconds, an FiCore extension)",
     *     type="int",
     *     minimum=2000,
     *     maximum=10000,
     *     example=3000,
     *     default=CallController::DEFAULT_AMD_INITIAL_SILENCE,
     * )
     */
    public string|int $MachineDetectionSilenceTimeout;

    public Core $core;

    public string $defaultHttpMethod;

    public function export(): RequestInterface
    {
        $request = new Originate\Request();
        $request->action = Originate\ActionEnum::Regular;

        if (isset($this->core)) {
            $request->core = $this->core;
        }

        $request->source = $this->From;

        if (isset($this->CallerName)) {
            $request->sourceNames = [$this->CallerName];
        }

        $request->destinations = [$this->To];

        if (isset($this->ExtraDialString)) {
            $request->extraDialString = $this->ExtraDialString;
        }

        if (isset($this->HangupOnRing)) {
            $request->onRingHangup = [(int)$this->HangupOnRing];
        }

        if (isset($this->MachineDetection)) {
            if ($this->MachineDetection === CallController::AMD_ENABLE) {
                $request->amd = true;
                $request->onAmdMachineHangup = false;
            } elseif ($this->MachineDetection === CallController::AMD_MSG_END) {
                $request->amd = true;
                $request->onAmdMachineHangup = true;
            }
        }

        if (isset($request->amd)) {
            $request->amdAsync = (bool)$this->AsyncAMD;

            if ($request->amdAsync) {
                if (isset($this->AsyncAmdStatusCallback, $this->AsyncAmdStatusCallbackMethod)) {
                    $request->onAmdAttn = "{$this->AsyncAmdStatusCallbackMethod}:{$this->AsyncAmdStatusCallback}";
                }
            }

            $request->amdTimeout = (int)$this->MachineDetectionTimeout;
            $request->amdInitialSilenceTimeout = (int)$this->MachineDetectionSilenceTimeout / 1000;
            $request->amdSilenceThreshold = (int)$this->MachineDetectionSpeechEndThreshold / 1000;
            $request->amdSpeechThreshold = (int)$this->MachineDetectionSpeechThreshold / 1000;
        }

        if (isset($this->SendDigits)) {
            if (isset($this->SendOnPreanswer) && $this->SendOnPreanswer) {
                $request->onMediaDTMF = [$this->SendDigits];
            } else {
                $request->onAnswerDTMF = [$this->SendDigits];
            }
        }

        if (isset($this->TimeLimit)) {
            $request->maxDuration = [(int)$this->TimeLimit];
        }

        $request->sequence = "{$this->defaultHttpMethod}:{$this->AnswerUrl}";

        if (isset($this->RingUrl)) {
            $request->onRingAttn = "{$this->defaultHttpMethod}:{$this->RingUrl}";
        }

        if (isset($this->HangupUrl)) {
            $request->onHangupAttn = "{$this->defaultHttpMethod}:{$this->HangupUrl}";
        }

        $gateways = explode(',', $this->Gateways);
        $gatewayCodecs = !empty($this->GatewayCodecs)
            ? str_getcsv($this->GatewayCodecs, ',', "'")
            : [];
        $gatewayTimeouts = !empty($this->GatewayTimeouts) ? explode(',', $this->GatewayTimeouts) : [];
        $gatewayRetries = !empty($this->GatewayRetries) ? explode(',', $this->GatewayRetries) : [];
        $request->gateways = [[]];

        foreach ($gateways as $gwIdx => $gateway) {
            $gw = new Gateway();
            $gw->name = $gateway;

            if (isset($gatewayCodecs[$gwIdx]) && strlen($gatewayCodecs[$gwIdx])) {
                $gw->codecs = $gatewayCodecs[$gwIdx];
            }

            if (isset($gatewayTimeouts[$gwIdx]) && strlen($gatewayTimeouts[$gwIdx])) {
                $gw->timeout = intval($gatewayTimeouts[$gwIdx]);
            }

            $gw->tries =
                (isset($gatewayRetries[$gwIdx]) && strlen($gatewayRetries[$gwIdx]))
                ? (int)$gatewayRetries[$gwIdx]
                : 1;

            $request->gateways[0][] = $gw;
        }

        if (isset($this->AccountSID)) {
            $request->tags['accountsid'] = $this->AccountSID;
        }

        return $request;
    }
}
