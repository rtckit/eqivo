<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\Eqivo\Core;
use RTCKit\Eqivo\Rest\Inquiry\RequestFactoryTrait;

/**
 * @OA\Schema(
 *     schema="GroupCallParameters",
 *     required={"Delimiter", "From", "To", "Gateways", "AnswerUrl"},
 * )
 */
class GroupCall
{
    use RequestFactoryTrait;

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
     * )
     */
    public string $AnswerUrl;

    /**
     * @OA\Property(
     *     description="Comma separated reject causes",
     *     example="USER_BUSY,NO_ANSWER",
     *     default="NO_ANSWER,ORIGINATOR_CANCEL,ALLOTTED_TIMEOUT,NO_USER_RESPONSE,CALL_REJECTED",
     * )
     */
    public string $RejectCauses;

    /**
     * @OA\Property(
     *     description="Caller Name to be set for the call",
     * )
     */
    public string $CallerName;

    /**
     * @OA\Property(
     *     description="Remote URL to fetch with POST HTTP request which must return a RestXML with Play, Wait and/or Speak Elements only (all others are ignored). This RESTXML is played to the called party when he answered",
     * )
     */
    public string $ConfirmSound;

    /**
     * @OA\Property(
     *     description="DTMF tone the called party must send to accept the call",
     * )
     */
    public string $ConfirmKey;

    /**
     * @OA\Property(
     *     description="Fully qualified URL to which the call hangup notification will be POSTed. `HangupCause` is added to the usual call [call notification parameters](#/components/schemas/CallNotificationParameters)",
     * )
     */
    public string $HangupUrl;

    /**
     * @OA\Property(
     *     description="Fully qualified URL to which the call ringing notification will be POSTed. `RequestUUID` and `CallUUID` is added to the usual [call notification parameters](#/components/schemas/CallNotificationParameters)",
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
     *     description="Core UUID of the desired FreeSWITCH instance (an Eqivo extension)",
     *     example="46ae8cd9-c28e-447d-ba40-a09cba49d474"
     * )
     */
    public string $CoreUUID;

    public Core $core;

    /** @var list<string> */
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
}
