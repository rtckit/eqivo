<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\ScheduledPlay;
use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait,
    PlaySoundsTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\SchedulePlay as SchedulePlayInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\SchedulePlay as SchedulePlayResponse;
use RTCKit\Eqivo\Rest\View\V0_1\SchedulePlay as SchedulePlayView;

use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use React\Promise\PromiseInterface;
use RTCKit\ESL;
use function React\Promise\{
    all,
    resolve
};

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/SchedulePlay/",
 *      summary="/v0.1/SchedulePlay/",
 *      description="Schedules media playback for a specific call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/SchedulePlayParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/SchedulePlayResponse"),
 *          ),
 *      ),
 * )
 */
class SchedulePlay implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;
    use PlaySoundsTrait;

    public const DEFAULT_LENGTH = 3600;

    public const DEFAULT_LEG = 'aleg';

    public const DEFAULT_DELIMITER = ',';

    protected SchedulePlayView $view;

    public function __construct()
    {
        $this->view = new SchedulePlayView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = SchedulePlayInquiry::factory($request);
                $response = new SchedulePlayResponse;

                $this->app->restServer->logger->debug('RESTAPI SchedulePlay with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function () use ($response) {
                        $response->Message ??= SchedulePlayResponse::MESSAGE_SUCCESS;
                        $response->Success ??= true;

                        return resolve($this->view->execute($response));
                    });
            })
            ->otherwise([$this, 'exceptionHandler']);
    }

    /**
     * Validates API call parameters
     *
     * @param SchedulePlayInquiry $inquiry
     * @param SchedulePlayResponse $response
     */
    public function validate(SchedulePlayInquiry $inquiry, SchedulePlayResponse $response): void
    {
        $inquiry->Loop = isset($inquiry->Loop) ? ($inquiry->Loop === 'true') : false;
        $inquiry->Mix = isset($inquiry->Mix) ? ($inquiry->Mix === 'false') : true;

        if (!isset($inquiry->CallUUID)) {
            $response->Message = SchedulePlayResponse::MESSAGE_NO_CALLUUID;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->Sounds)) {
            $response->Message = SchedulePlayResponse::MESSAGE_NO_SOUNDS;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->Time)) {
            $response->Message = SchedulePlayResponse::MESSAGE_NO_TIME;
            $response->Success = false;

            return;
        }

        $inquiry->Time = (int)$inquiry->Time;

        if ($inquiry->Time < 1) {
            $response->Message = SchedulePlayResponse::MESSAGE_INVALID_TIME;
            $response->Success = false;

            return;
        }

        $inquiry->Legs ??= static::DEFAULT_LEG;

        if (!in_array($inquiry->Legs, ['aleg', 'bleg', 'both'])) {
            $response->Message = SchedulePlayResponse::MESSAGE_INVALID_LEG;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->Length)) {
            $inquiry->Length = static::DEFAULT_LENGTH;
        } else {
            $inquiry->Length = (int)$inquiry->Length;

            if ($inquiry->Length < 1) {
                $response->Message = SchedulePlayResponse::MESSAGE_INVALID_LENGTH;
                $response->Success = false;

                return;
            }
        }

        $inquiry->delimiter ??= static::DEFAULT_DELIMITER;
        $inquiry->soundList = explode($inquiry->delimiter, $inquiry->Sounds);

        if (!isset($inquiry->soundList[0])) {
            $response->Message = SchedulePlayResponse::MESSAGE_INVALID_SOUNDS;
            $response->Success = false;
        }

        foreach ($inquiry->soundList as $key => $path) {
            if (strpos($path, 'http_cache://') === 0) {
                $inquiry->soundList[$key] = str_replace(' ', '%20', $path);
            }
        }

        $inquiry->aLegFlags = $inquiry->bLegFlags = '';

        if ($inquiry->Loop) {
            $inquiry->aLegFlags .= 'l';
            $inquiry->bLegFlags .= 'l';
        }

        if ($inquiry->Mix) {
            $inquiry->aLegFlags .= 'm';
            $inquiry->bLegFlags .= 'mr';
        } else {
            $inquiry->bLegFlags .= 'r';
        }

        $playStr = implode('!', $inquiry->soundList);
        $inquiry->playStringALeg = 'file_string://' . $playStr;
        $inquiry->playStringBLeg = 'file_string://silence_stream://1!' . $playStr;

        $session = $this->app->getSession($inquiry->CallUUID);

        if (!isset($session)) {
            $response->Message = SchedulePlayResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->session = $session;
        $inquiry->core = $session->core;
    }

    /**
     * Performs the API call function
     *
     * @param SchedulePlayInquiry $inquiry
     * @param SchedulePlayResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(SchedulePlayInquiry $inquiry, SchedulePlayResponse $response): PromiseInterface
    {
        $schedPlay = new ScheduledPlay;
        $schedPlay->uuid = Uuid::uuid4()->toString();
        $schedPlay->timeout = (int)$inquiry->Time;
        $response->SchedPlayId = $schedPlay->uuid;

        $inquiry->core->addScheduledPlay($schedPlay);

        return $this->getPlayCommands($inquiry)
            ->then (function (array $commands) use ($inquiry, $response, $schedPlay): PromiseInterface {
                $promises = [];

                foreach ($commands as $command) {
                    $command = "sched_api +{$inquiry->Time} {$schedPlay->uuid} {$command}";

                    $promises[] = $inquiry->core->client->api((new ESL\Request\Api())->setParameters($command))
                        ->then(function (ESL\Response\ApiResponse $eslResponse) use ($response, $command): PromiseInterface {
                                if (!$eslResponse->isSuccessful()) {
                                    $response->Message = SchedulePlayResponse::MESSAGE_FAILED;
                                    $response->Success = false;

                                    $this->app->restServer->logger->error("SchedulePlay Failed '{$command}': " . ($eslResponse->getBody() ?? '<null>'));
                                }

                                return resolve();
                        });
                }

                return all($promises);
            });
    }
}
