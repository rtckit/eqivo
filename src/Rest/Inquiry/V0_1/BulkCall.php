<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\FiCore\Command\Channel\Originate;

use RTCKit\FiCore\Command\RequestInterface;
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\FiCore\Switch\{
    Core,
    Gateway,
};

/**
 * @OA\Schema(
 *     schema="BulkCallParameters",
 *     required={"Delimiter", "From", "To", "Gateways", "AnswerUrl"},
 * )
 */
class BulkCall extends AbstractInquiry
{
    public string $AccountSID;

    /**
     * @var non-empty-string
     * @OA\Property(
     *     description="Any character, except `/` and `,`, which will be used as a separator within several parameters",
     *     example="<",
     * )
     */
    public string $Delimiter;

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
     *     example="Bulk Test",
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
     *     example="2e8e6275-7cfe-4e3c-a8d6-25316b4519c7"
     * )
     */
    public string $CoreUUID;

    public Core $core;

    /** @var non-empty-array<int, string> */
    public array $toList;

    /** @var list<string> */
    public array $gwList;

    /** @var list<string> */
    public array $gwCodecsList;

    /** @var list<string> */
    public array $gwTimeoutsList;

    /** @var list<string> */
    public array $gwRetriesList;

    /** @var list<string> */
    public array $sendDigitsList;

    /** @var list<string> */
    public array $sendPreanswerList;

    /** @var list<string> */
    public array $hupOnRingList;

    /** @var list<string> */
    public array $callerNameList;

    /** @var list<string> */
    public array $timeLimitList;

    public string $defaultHttpMethod;

    public function export(): RequestInterface
    {
        $request = new Originate\Request();
        $request->action = Originate\ActionEnum::Regular;

        if (isset($this->core)) {
            $request->core = $this->core;
        }

        $request->source = $this->From;

        if (isset($this->callerNameList)) {
            $request->sourceNames = $this->callerNameList;
        }

        $request->destinations = $this->toList;

        if (isset($this->ExtraDialString)) {
            $request->extraDialString = $this->ExtraDialString;
        }

        if (isset($this->HangupOnRing)) {
            $request->onRingHangup = array_map('intval', $this->hupOnRingList);
        }

        if (isset($this->sendDigitsList)) {
            foreach ($this->sendDigitsList as $idx => $sendDigits) {
                if (isset($this->sendPreanswerList, $this->sendPreanswerList[$idx]) && $this->sendPreanswerList[$idx]) {
                    /** @psalm-suppress PropertyTypeCoercion */
                    $request->onMediaDTMF[$idx] = $sendDigits;
                } else {
                    /** @psalm-suppress PropertyTypeCoercion */
                    $request->onAnswerDTMF[$idx] = $sendDigits;
                }
            }
        }

        if (isset($this->TimeLimit)) {
            $request->maxDuration = array_map('intval', $this->timeLimitList);
        }

        $request->sequence = "{$this->defaultHttpMethod}:{$this->AnswerUrl}";

        if (isset($this->RingUrl)) {
            $request->onRingAttn = "{$this->defaultHttpMethod}:{$this->RingUrl}";
        }

        if (isset($this->HangupUrl)) {
            $request->onHangupAttn = "{$this->defaultHttpMethod}:{$this->HangupUrl}";
        }

        $request->gateways = [[]];

        foreach ($this->gwList as $destIdx => $destGateways) {
            /** @psalm-suppress PropertyTypeCoercion */
            $request->gateways[$destIdx] = [];
            $gateways = explode(',', $destGateways);
            $gatewayCodecs = !empty($this->gwCodecsList[$destIdx])
                ? str_getcsv($this->gwCodecsList[$destIdx], ',', "'")
                : [];
            $gatewayTimeouts = !empty($this->gwTimeoutsList[$destIdx]) ? explode(',', $this->gwTimeoutsList[$destIdx]) : [];
            $gatewayRetries = !empty($this->gwRetriesList[$destIdx]) ? explode(',', $this->gwRetriesList[$destIdx]) : [];

            foreach ($gateways as $gwIdx => $gateway) {
                $gw = new Gateway();
                $gw->name = $gateway;

                if (!empty($gatewayCodecs[$gwIdx])) {
                    $gw->codecs = $gatewayCodecs[$gwIdx];
                }

                if (!empty($gatewayTimeouts[$gwIdx])) {
                    $gw->timeout = intval($gatewayTimeouts[$gwIdx]);
                }

                $gw->tries = empty($gatewayRetries[$gwIdx]) ? 1 : (int)$gatewayRetries[$gwIdx];

                /** @psalm-suppress PropertyTypeCoercion */
                $request->gateways[$destIdx][] = $gw;
            }
        }

        if (isset($this->AccountSID)) {
            $request->tags['accountsid'] = $this->AccountSID;
        }

        return $request;
    }
}
