<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait,
    PlaySoundsTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\Play as PlayInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\Play as PlayResponse;
use RTCKit\Eqivo\Rest\View\V0_1\Play as PlayView;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use RTCKit\ESL;
use function React\Promise\{
    all,
    resolve
};

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/Play/",
 *      summary="/v0.1/Play/",
 *      description="Plays media into a live call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/PlayParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/PlayResponse"),
 *          ),
 *      ),
 * )
 */
class Play implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;
    use PlaySoundsTrait;

    public const DEFAULT_LENGTH = 3600;

    public const DEFAULT_LEG = 'aleg';

    public const DEFAULT_DELIMITER = ',';

    protected PlayView $view;

    public function __construct()
    {
        $this->view = new PlayView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = PlayInquiry::factory($request);
                $response = new PlayResponse;

                $this->app->restServer->logger->debug('RESTAPI Play with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function () use ($response) {
                        $response->Message ??= PlayResponse::MESSAGE_SUCCESS;
                        $response->Success ??= true;

                        return resolve($this->view->execute($response));
                    });
            })
            ->otherwise([$this, 'exceptionHandler']);
    }

    /**
     * Validates API call parameters
     *
     * @param PlayInquiry $inquiry
     * @param PlayResponse $response
     */
    public function validate(PlayInquiry $inquiry, PlayResponse $response): void
    {
        $inquiry->Loop = isset($inquiry->Loop) ? ($inquiry->Loop === 'true') : false;
        $inquiry->Mix = isset($inquiry->Mix) ? ($inquiry->Mix === 'false') : true;

        if (!isset($inquiry->CallUUID)) {
            $response->Message = PlayResponse::MESSAGE_NO_CALLUUID;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->Sounds)) {
            $response->Message = PlayResponse::MESSAGE_NO_SOUNDS;
            $response->Success = false;

            return;
        }

        $inquiry->Legs ??= static::DEFAULT_LEG;

        if (!in_array($inquiry->Legs, ['aleg', 'bleg', 'both'])) {
            $response->Message = PlayResponse::MESSAGE_INVALID_LEG;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->Length)) {
            $inquiry->Length = static::DEFAULT_LENGTH;
        } else {
            $inquiry->Length = (int)$inquiry->Length;

            if ($inquiry->Length < 1) {
                $response->Message = PlayResponse::MESSAGE_INVALID_LENGTH;
                $response->Success = false;

                return;
            }
        }

        $inquiry->delimiter ??= static::DEFAULT_DELIMITER;
        $inquiry->soundList = explode($inquiry->delimiter, $inquiry->Sounds);

        if (!isset($inquiry->soundList[0])) {
            $response->Message = PlayResponse::MESSAGE_INVALID_SOUNDS;
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
            $response->Message = PlayResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->session = $session;
        $inquiry->core = $session->core;
    }

    /**
     * Performs the API call function
     *
     * @param PlayInquiry $inquiry
     * @param PlayResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(PlayInquiry $inquiry, PlayResponse $response): PromiseInterface
    {
        return $this->getPlayCommands($inquiry)
            ->then (function (array $commands) use ($inquiry, $response): PromiseInterface {
                $promises = [];

                foreach ($commands as $command) {
                    $promises[] = $inquiry->core->client->api((new ESL\Request\Api())->setParameters($command))
                        ->then(function (ESL\Response\ApiResponse $eslResponse) use ($response, $command): PromiseInterface {
                                if (!$eslResponse->isSuccessful()) {
                                    $response->Message = PlayResponse::MESSAGE_FAILED;
                                    $response->Success = false;

                                    $this->app->restServer->logger->error("Play Failed '{$command}': " . ($eslResponse->getBody() ?? '<null>'));
                                }

                                return resolve();
                        });
                }

                return all($promises);
            });
    }
}
