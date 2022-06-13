<?php

declare(strict_types=1);

namespace RTCKit\Eqivo;

use RTCKit\Eqivo\Outbound\RestXmlElement;

use React\EventLoop\TimerInterface;
use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\ESL;
use RTCKit\React\ESL\RemoteOutboundClient;
use stdClass as Event;
use function React\Promise\resolve;

/** @OA\Schema(
 *      schema="CallNotificationParameters",
 *      required={"To", "Direction", "From", "CallerName", "CallUUID", "CallStatus", "CoreUUID", "RestApiServer"},
 *      @OA\Property(
 *          property="RestApiServer",
 *          description="Eqivo Rest API server which controls the call (Eqivo extension)",
 *          type="string",
 *          example="localhost",
 *      ),
 *  )
 */
class Session
{
    public static int $instances = 0;

    public App $app;

    /**
     * @OA\Property(
     *      property="CoreUUID",
     *      description="FreeSWITCH's instance unique identifier (Eqivo extension)",
     *      type="string",
     *      example="b14e0893-98ef-44b6-8e4b-e4bcf937bfa9",
     * )
     */
    public Core $core;

    public RemoteOutboundClient $client;

    /** @var array<string, string> */
    public array $context;

    public string $coreUuid;

    /**
     * @OA\Property(
     *      property="CallUUID",
     *      description="Call's unique identifier, assigned by FreeSWITCH",
     *      example="7ff22856-357e-4b77-812c-22d28cbb8982",
     * )
     */
    public string $uuid;

    /**
     * @OA\Property(
     *      property="CallStatus",
     *      description="Call's current status",
     *      type="string",
     *      enum={"ringing", "early-media", "answer", "in-progress", "completed"},
     *      example="in-progress",
     * )
     */
    public StatusEnum $status;

    public bool $answered = false;

    public string $accountSid;

    /**
     * @OA\Property(
     *      property="ALegUUID",
     *      description="A leg call's unique identifier, assigned by FreeSWITCH",
     *      example="8dd5a1e0-6621-4a07-98b3-e033b014fba7",
     * )
     */
    public string $aLegUuid;

    /**
     * @OA\Property(
     *      property="ALegRequestUUID",
     *      description="A leg call request's unique identifier",
     *      example="5a4ef7cf-dc08-4cd3-a9d0-6d1555ba3309",
     * )
     */
    public string $aLegRequestUuid;

    public string $callerNumber;

    /**
     * @OA\Property(
     *      property="CallerName",
     *      description="Caller name set for the call",
     *      example="Alice",
     * )
     */
    public string $callerName;

    /**
     * @OA\Property(
     *      property="From",
     *      description="Caller ID set for the call",
     *      example="15557654321",
     * )
     */
    public string $from;

    public string $calledNumber;

    /**
     * @OA\Property(
     *      property="To",
     *      description="Called phone number",
     *      example="15551234567",
     * )
     */
    public string $to;

    /**
     * @OA\Property(
     *      property="ScheduledHangupId",
     *      description="Unique identifier of the scheduled hangup task",
     *      example="8f4a3488-3283-408b-bf6f-b957d5d4cf00",
     * )
     */
    public string $schedHangupId = '';

    public string $defaultHangupUrl;

    public string $hangupUrl;

    /**
     * @OA\Property(
     *      property="ForwardedFrom",
     *      description="Original call destination (before diversion)",
     *      example="15551239876",
     * )
     */
    public string $forwardedFrom;

    /**
     * @OA\Property(
     *      property="Direction",
     *      description="Call's direction",
     *      example="15551234567",
     *      type="string",
     *      enum={"inbound", "outbound"},
     *      example="outbound",
     * )
     */
    public bool $outbound;

    public HangupCauseEnum $hangupCause;

    public string $targetUrl;

    public RestXmlElement $restXml;

    public string $currentElement;

    /** @var array<?Event> */
    public array $eventQueue = [];

    public Deferred $eventQueueDeferred;

    public TimerInterface $eventQueueTimer;

    public bool $raiseExceptionOnHangup = false;

    /** @var array<string, string> */
    protected array $cachedPayload;

    public bool $sipTransfer;

    public string $sipTransferUri;

    public bool $transferInProgress;

    public bool $preAnswer = false;

    public TimerInterface $avmdTimer;

    public int $amdDuration;

    public string $amdAnsweredBy;

    public bool $amdAsync;

    public string $amdMethod;

    public string $amdUrl;

    function __construct() {
        self::$instances++;
    }

    function __destruct() {
        self::$instances--;
    }

    /**
     * @return array<string, string>
     */
    public function getPayload(): array
    {
        if (!isset($this->cachedPayload)) {
            $this->cachedPayload = [
                'To' => $this->to,
                'Direction' => $this->outbound ? 'outbound' : 'inbound',
                'From' => $this->from,
                'CallerName' => $this->callerName,
                'CallUUID' => $this->uuid,
                'CallStatus' => '',
                'CoreUUID' => $this->coreUuid,
                'RestApiServer' => $this->app->config->restServerAdvertisedHost,
            ];

            if (isset($this->aLegUuid, $this->aLegUuid[0])) {
                $this->cachedPayload['ALegUUID'] = $this->aLegUuid;
            }

            if (isset($this->aLegRequestUuid, $this->aLegRequestUuid[0])) {
                $this->cachedPayload['ALegRequestUUID'] = $this->aLegRequestUuid;
            }

            if (isset($this->schedHangupId, $this->schedHangupId[0])) {
                $this->cachedPayload['ScheduledHangupId'] = $this->schedHangupId;
            }

            if (isset($this->forwardedFrom, $this->forwardedFrom[0])) {
                $this->cachedPayload['ForwardedFrom'] = $this->forwardedFrom;
            }

            foreach ($this->app->config->extraChannelVars as $var) {
                $loVar = strtolower($var);

                if (isset($this->context[$loVar])) {
                    $this->cachedPayload[$var] = $this->context[$loVar];
                }
            }
        }

        $this->cachedPayload['CallStatus'] = $this->status->value;

        return $this->cachedPayload;
    }

    public function bgApi(ESL\Request\BgApi $request): PromiseInterface
    {
        return $this->client->bgApi($request)
            ->then(function (ESL\Response $response) use ($request): PromiseInterface {
                if ($response instanceof ESL\Response\CommandReply) {
                    $uuid = $response->getHeader('job-uuid');

                    if (!is_null($uuid)) {
                        $job = new Job;
                        $job->uuid = $uuid;
                        $job->command = explode(' ', $request->getParameters() ?? '')[0];

                        $this->core->addJob($job);
                    }
                }

                return resolve($response);
            });
    }
}
