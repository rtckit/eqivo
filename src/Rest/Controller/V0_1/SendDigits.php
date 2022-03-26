<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\{
    App,
    HangupCauseEnum
};
use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\SendDigits as SendDigitsInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\SendDigits as SendDigitsResponse;
use RTCKit\Eqivo\Rest\View\V0_1\SendDigits as SendDigitsView;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use RTCKit\ESL;
use function React\Promise\resolve;

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/SendDigits/",
 *      summary="/v0.1/SendDigits/",
 *      description="Emits DMTF tones to a call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/SendDigitsParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/SendDigitsResponse"),
 *          ),
 *      ),
 * )
 */
class SendDigits implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;

    public const DEFAULT_LEG = 'aleg';

    protected SendDigitsView $view;

    public function __construct()
    {
        $this->view = new SendDigitsView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = SendDigitsInquiry::factory($request);
                $response = new SendDigitsResponse;

                $this->app->restServer->logger->debug('RESTAPI SendDigits with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function () use ($response) {
                        $response->Message ??= SendDigitsResponse::MESSAGE_SUCCESS;
                        $response->Success ??= true;

                        return resolve($this->view->execute($response));
                    });
            })
            ->otherwise([$this, 'exceptionHandler']);
    }

    /**
     * Validates API call parameters
     *
     * @param SendDigitsInquiry $inquiry
     * @param SendDigitsResponse $response
     */
    public function validate(SendDigitsInquiry $inquiry, SendDigitsResponse $response): void
    {
        if (!isset($inquiry->CallUUID)) {
            $response->Message = SendDigitsResponse::MESSAGE_NO_CALLUUID;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->Digits)) {
            $response->Message = SendDigitsResponse::MESSAGE_NO_DIGITS;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->Leg)) {
            $inquiry->Leg = static::DEFAULT_LEG;
        } else {
            if (!in_array($inquiry->Leg, ['aleg', 'bleg'])) {
                $response->Message = SendDigitsResponse::MESSAGE_INVALID_LEG;
                $response->Success = false;

                return;
            }
        }

        $session = $this->app->getSession($inquiry->CallUUID);

        if (!isset($session)) {
            $response->Message = SendDigitsResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->session = $session;
        $inquiry->core = $session->core;
    }

    /**
     * Performs the API call function
     *
     * @param SendDigitsInquiry $inquiry
     * @param SendDigitsResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(SendDigitsInquiry $inquiry, SendDigitsResponse $response): PromiseInterface
    {
        $cmd = (($inquiry->Leg === 'aleg') ? 'uuid_send_dtmf' : 'uuid_recv_dtmf') .
            " {$inquiry->CallUUID} {$inquiry->Digits}";

        return $inquiry->core->client->bgApi((new ESL\Request\BgApi())->setParameters($cmd))
            ->then(function (ESL\Response $eslResponse) use ($response): PromiseInterface {
                $uuid = null;

                if ($eslResponse instanceof ESL\Response\CommandReply) {
                    $uuid = $eslResponse->getHeader('job-uuid');
                }

                if (!isset($uuid)) {
                    $response->Message = SendDigitsResponse::MESSAGE_FAILED;
                    $response->Success = false;
                }

                return resolve();
            });
    }
}
