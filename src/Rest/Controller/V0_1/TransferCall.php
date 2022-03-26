<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\TransferCall as TransferCallInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\TransferCall as TransferCallResponse;
use RTCKit\Eqivo\Rest\View\V0_1\TransferCall as TransferCallView;

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
 *      path="/v0.1/TransferCall/",
 *      summary="/v0.1/TransferCall/",
 *      description="Replaces the RestXML flow of a live call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/TransferCallParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/TransferCallResponse"),
 *          ),
 *      ),
 * )
 */
class TransferCall implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;

    protected TransferCallView $view;

    public function __construct()
    {
        $this->view = new TransferCallView;
    }

    /**
     * API call entrypoint
     *
     * @param ServerRequestInterface $request
     *
     * @return PromiseInterface
     */
    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = TransferCallInquiry::factory($request);
                $response = new TransferCallResponse;

                $this->app->restServer->logger->debug('RESTAPI TransferCall with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function (?ESL\Response\ApiResponse $eslResponse = null) use ($response, $inquiry) {
                        if (!isset($eslResponse) || !$eslResponse->isSuccessful()) {
                            $response->Message = TransferCallResponse::MESSAGE_FAILED;
                            $response->Success = false;

                            unset($inquiry->session->transferInProgress);
                        } else {
                            $response->Message = TransferCallResponse::MESSAGE_SUCCESS;
                            $response->Success = true;
                        }

                        return resolve($this->view->execute($response));
                    });
            })
            ->otherwise([$this, 'exceptionHandler']);
    }

    /**
     * Validates API call inquiry
     *
     * @param TransferCallInquiry $inquiry
     * @param TransferCallResponse $response
     */
    public function validate(TransferCallInquiry $inquiry, TransferCallResponse $response): void
    {
        if (!isset($inquiry->CallUUID)) {
            $response->Message = TransferCallResponse::MESSAGE_NO_CALLUUID;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->Url)) {
            $response->Message = TransferCallResponse::MESSAGE_NO_URL;
            $response->Success = false;

            return;
        }

        if (!(filter_var($inquiry->Url, FILTER_VALIDATE_URL))) {
            $response->Message = TransferCallResponse::MESSAGE_INVALID_URL;
            $response->Success = false;

            return;
        }

        $session = $this->app->getSession($inquiry->CallUUID);

        if (!isset($session)) {
            $response->Message = TransferCallResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->session = $session;
        $inquiry->core = $session->core;
    }

    /**
     * Performs the API call function
     *
     * @param TransferCallInquiry $inquiry
     * @param TransferCallResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(TransferCallInquiry $inquiry, TransferCallResponse $response): PromiseInterface
    {
        return all([
            'transfer_progress' => $inquiry->core->client->api(
                (new ESL\Request\Api)->setParameters("uuid_setvar {$inquiry->CallUUID} {$this->app->config->appPrefix}_transfer_progress true")
            ),
            'transfer_url' => $inquiry->core->client->api(
                (new ESL\Request\Api)->setParameters("uuid_setvar {$inquiry->CallUUID} {$this->app->config->appPrefix}_transfer_url " . $inquiry->Url)
            ),
            $this->app->config->appPrefix . '_destination_number' => $inquiry->core->client->api(
                (new ESL\Request\Api)->setParameters("uuid_getvar {$inquiry->CallUUID} {$this->app->config->appPrefix}_destination_number")
            ),
            'destination_number' => $inquiry->core->client->api(
                (new ESL\Request\Api)->setParameters("uuid_getvar {$inquiry->CallUUID} destination_number")
            ),
        ])
            ->then(function (array $args) use ($inquiry): PromiseInterface {
                $destNumber = $args[$this->app->config->appPrefix . '_destination_number']->getBody();

                if (($destNumber === '_undef_') || (strpos($destNumber, '-ERR') === 0)) {
                    $destNumber = $args['destination_number']->getBody();

                    return $inquiry->core->client->api(
                        (new ESL\Request\Api)->setParameters("uuid_setvar {$inquiry->CallUUID} {$this->app->config->appPrefix}_destination_number " . $destNumber)
                    );
                }

                return resolve();
            })
            ->then(function () use ($inquiry): PromiseInterface {
                $inquiry->session->transferInProgress = true;

                return $inquiry->core->client->api(
                    (new ESL\Request\Api)->setParameters("uuid_transfer {$inquiry->CallUUID} 'sleep:5000' inline")
                );
            });
    }
}
