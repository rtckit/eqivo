<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ConferenceXmlParserTrait,
    ControllerInterface,
    ErrorableTrait
};
use RTCKit\Eqivo\Rest\Inquiry\V0_1\ConferenceListMembers as ConferenceListMembersInquiry;
use RTCKit\Eqivo\Rest\Response\V0_1\ConferenceListMembers as ConferenceListMembersResponse;
use RTCKit\Eqivo\Rest\View\V0_1\ConferenceListMembers as ConferenceListMembersView;

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
 *      path="/v0.1/ConferenceListMembers/",
 *      summary="/v0.1/ConferenceListMembers/",
 *      description="Retrieves the member list for a given conference",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/ConferenceListMembersParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/ConferenceListMembersResponse"),
 *          ),
 *      ),
 * )
 */
class ConferenceListMembers implements ControllerInterface
{
    use AuthenticatedTrait;
    use ErrorableTrait;
    use ConferenceXmlParserTrait;

    protected ConferenceListMembersView $view;

    public function __construct()
    {
        $this->view = new ConferenceListMembersView;
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        return $this->authenticate($request)
            ->then(function () use ($request): PromiseInterface {
                $inquiry = ConferenceListMembersInquiry::factory($request);
                $response = new ConferenceListMembersResponse;

                $this->app->restServer->logger->debug('RESTAPI ConferenceListMembers with ' . json_encode($inquiry));
                $this->validate($inquiry, $response);

                if (isset($response->Success) && !$response->Success) {
                    return resolve($this->view->execute($response));
                }

                return $this->perform($inquiry, $response)
                    ->then(function () use ($response) {
                        $response->Message ??= ConferenceListMembersResponse::MESSAGE_SUCCESS;
                        $response->Success ??= true;

                        return resolve($this->view->execute($response));
                    });
            })
            ->otherwise([$this, 'exceptionHandler']);
    }

    /**
     * Validates API call parameters
     *
     * @param ConferenceListMembersInquiry $inquiry
     * @param ConferenceListMembersResponse $response
     */
    public function validate(ConferenceListMembersInquiry $inquiry, ConferenceListMembersResponse $response): void
    {
        if (!isset($inquiry->ConferenceName)) {
            $response->Message = ConferenceListMembersResponse::MESSAGE_NO_CONFERENCE_NAME;
            $response->Success = false;

            return;
        }

        $conference = $this->app->getConference($inquiry->ConferenceName);

        if (!isset($conference)) {
            $response->Message = ConferenceListMembersResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->core = $conference->core;
    }

    /**
     * Performs the API call function
     *
     * @param ConferenceListMembersInquiry $inquiry
     * @param ConferenceListMembersResponse $response
     *
     * @return PromiseInterface
     */
    public function perform(ConferenceListMembersInquiry $inquiry, ConferenceListMembersResponse $response): PromiseInterface
    {
        $members = isset($inquiry->MemberFilter) ? explode(',', $inquiry->MemberFilter) : [];
        $calls = isset($inquiry->CallUUIDFilter) ? explode(',', $inquiry->CallUUIDFilter) : [];
        $muted = isset($inquiry->MutedFilter) ? ($inquiry->MutedFilter === 'true') : false;
        $deaf = isset($inquiry->DeafFilter) ? ($inquiry->DeafFilter === 'true') : false;

        return $inquiry->core->client->api(
            (new ESL\Request\Api())->setParameters("conference {$inquiry->ConferenceName} xml_list")
        )
            ->then(function (ESL\Response\ApiResponse $eslResponse) use ($response, $inquiry, $members, $calls, $muted, $deaf): PromiseInterface {
                $body = $eslResponse->getBody();

                if (!isset($body)) {
                    $this->app->restServer->logger->warning('Conference ListMembers Failed for ' . $inquiry->ConferenceName. ', empty response');

                    $response->Message = ConferenceListMembersResponse::MESSAGE_PARSE_ERROR;
                    $response->Success = false;

                    return resolve();
                }

                $xml = simplexml_load_string($body);

                if ($xml === false) {
                    $this->app->restServer->logger->warning('Conference ListMembers Failed for ' . $inquiry->ConferenceName . ', parsing error');

                    $response->Message = ConferenceListMembersResponse::MESSAGE_PARSE_ERROR;
                    $response->Success = false;

                    return resolve();
                }

                $response->List = $this->parseXmlList($inquiry->core, $xml, $members, $calls, $muted, $deaf);
                $this->app->restServer->logger->debug('Conference ListMembers Done for ' . $inquiry->ConferenceName);

                return resolve();
            });
    }
}
