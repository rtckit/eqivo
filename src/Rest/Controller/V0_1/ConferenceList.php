<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ConferenceXmlParserTrait,
    ControllerInterface,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\ConferenceList as ConferenceListInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\ConferenceList as ConferenceListResponse;
use RTCKit\Eqivo\Rest\View\V0_1\ConferenceList as ConferenceListView;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use RTCKit\ESL;
use function React\Promise\{
    all,
    resolve
};

/**
 * @OA\Post(
 *      tags={"Conference"},
 *      path="/v0.1/ConferenceList/",
 *      summary="/v0.1/ConferenceList/",
 *      description="Returns a list of all established conferences",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/ConferenceListParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/ConferenceListResponse"),
 *          ),
 *      ),
 * )
 */
class ConferenceList implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;
    use ConferenceXmlParserTrait;

    protected ConferenceListView $view;

    public function __construct()
    {
        $this->view = new ConferenceListView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = ConferenceListInquiry::factory($request);
                $response = new ConferenceListResponse;

                $this->app->restServer->logger->debug('RESTAPI ConferenceList with ' . json_encode($inquiry));

                return $this->perform($inquiry, $response)
                    ->then(function () use ($response) {
                        $response->Message ??= ConferenceListResponse::MESSAGE_SUCCESS;
                        $response->Success ??= true;

                        return resolve($this->view->execute($response));
                    });
            })
            ->otherwise([$this, 'exceptionHandler']);
    }

    /**
     * Performs the API call function
     *
     * @param ConferenceListInquiry $inquiry
     * @param ConferenceListResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(ConferenceListInquiry $inquiry, ConferenceListResponse $response): PromiseInterface
    {
        $members = isset($inquiry->MemberFilter) ? explode(',', $inquiry->MemberFilter) : [];
        $calls = isset($inquiry->CallUUIDFilter) ? explode(',', $inquiry->CallUUIDFilter) : [];
        $muted = isset($inquiry->MutedFilter) ? ($inquiry->MutedFilter === 'true') : false;
        $deaf = isset($inquiry->DeafFilter) ? ($inquiry->DeafFilter === 'true') : false;
        $cores = $this->app->getAllCores();
        $promises = [];

        foreach ($cores as $core) {
            $promises[] = $core->client->api(
                (new ESL\Request\Api())->setParameters("conference xml_list")
            )
                ->then(function (ESL\Response\ApiResponse $eslResponse) use ($core, $response, $members, $calls, $muted, $deaf): PromiseInterface {
                    $body = $eslResponse->getBody();

                    if (!isset($body)) {
                        $this->app->restServer->logger->warning('Conference List Failed for ' . $core->uuid . ', empty response');

                        $response->Message = ConferenceListResponse::MESSAGE_PARSE_ERROR;
                        $response->Success = false;

                        return resolve();
                    }

                    $xml = simplexml_load_string($body);

                    if ($xml === false) {
                        $this->app->restServer->logger->warning('Conference List Failed for ' . $core->uuid . ', parsing error');

                        $response->Message = ConferenceListResponse::MESSAGE_PARSE_ERROR;
                        $response->Success = false;

                        return resolve();
                    }

                    $response->List = array_merge($response->List, $this->parseXmlList($core, $xml, $members, $calls, $muted, $deaf));
                    $this->app->restServer->logger->debug('Conference List Done for ' . $core->uuid);

                    return resolve();
                });
        }

        return all($promises);
    }
}
