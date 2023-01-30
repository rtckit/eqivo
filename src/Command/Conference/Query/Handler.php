<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Command\Conference\Query;

use React\Promise\PromiseInterface;
use function React\Promise\{
    all,
    resolve,
};

use RTCKit\ESL;
use RTCKit\FiCore\Command\{
    AbstractHandler,
    RequestInterface,
};
use RTCKit\FiCore\Switch\Core;

use SimpleXMLElement;

class Handler extends AbstractHandler
{
    public function execute(RequestInterface $request): PromiseInterface
    {
        assert($request instanceof Request);

        $response = new Response();
        $response->successful = true;

        if ($request->action !== ActionEnum::Members) {
            $response->successful = false;

            return resolve($response);
        }

        $promises = [];
        $cores = $this->app->getAllCores();
        $command = 'conference xml_list';

        if (isset($request->conference)) {
            $cores = [$request->conference->core];
            $command = "conference {$request->conference->room} xml_list";
        }

        foreach ($cores as $core) {
            $promises[] = $core->client->api((new ESL\Request\Api())->setParameters($command))
                ->then(function (ESL\Response\ApiResponse $eslResponse) use ($request, $core, $response): PromiseInterface {
                    $body = $eslResponse->getBody();

                    if (!isset($body)) {
                        $this->app->commandConsumer->logger->warning('Conference List Failed for ' . $core->uuid . ', empty response');

                        $response->successful = false;

                        return resolve();
                    }

                    $xml = simplexml_load_string($body);

                    if ($xml === false) {
                        $this->app->commandConsumer->logger->warning('Conference List Failed for ' . $core->uuid . ', parsing error');

                        $response->successful = false;

                        return resolve();
                    }

                    $response->rooms = array_merge($response->rooms, $this->parseXmlList($core, $xml, $request->members, $request->channels, $request->muted, $request->deaf));
                    $this->app->commandConsumer->logger->debug('Conference List Done for ' . $core->uuid);

                    return resolve();
                });
        }

        return all($promises)
            ->then(function () use ($response): Response {
                return $response;
            });
    }

    /**
     * Parses the output of `xml_list`
     *
     * @param Core $core
     * @param SimpleXMLElement $xml
     * @param list<string> $members
     * @param list<string> $calls
     * @param bool $muted
     * @param bool $deaf
     *
     * @return array<string, mixed>
     */
    protected function parseXmlList(Core $core, SimpleXMLElement $xml, array $members, array $calls, bool $muted, bool $deaf): array
    {
        $ret = [];

        foreach ($xml->conference as $conference) {
            $attrs = $conference->attributes();
            $conf = [
                'CoreUUID' => $core->uuid,
                'ConferenceUUID' => isset($attrs['uuid']) ? (string)$attrs['uuid'] : '',
                'ConferenceRunTime' => isset($attrs['run_time']) ? (string)$attrs['run_time'] : '',
                'ConferenceName' => isset($attrs['name']) ? (string)$attrs['name'] : '',
                'ConferenceMemberCount' => isset($attrs['member-count']) ? (string)$attrs['member-count'] : '',
                'Members' => [],
            ];

            foreach ($conference->members->member as $member) {
                if (!isset($member->id) || !isset($member->uuid)) {
                    continue;
                }

                $memberId = (string)$member->id;
                $callUuid = (string)$member->uuid;
                $isMuted = (string)$member->can_speak === 'false';
                $isDeaf = (string)$member->can_hear === 'false';

                if (isset($members[0]) && !in_array($memberId, $members)) {
                    continue;
                }

                if (isset($calls[0]) && !in_array($callUuid, $calls)) {
                    continue;
                }

                if ($muted && !$isMuted) {
                    continue;
                }

                if ($deaf && !$isDeaf) {
                    continue;
                }

                $conf['Members'][] = [
                    'MemberID' => $memberId,
                    'Deaf' => $isDeaf,
                    'Muted' => $isMuted,
                    'CallUUID' => $callUuid,
                    'CallName' => (string)$member->caller_id_name,
                    'CallNumber' => (string)$member->caller_id_number,
                    'JoinTime' => (string)$member->join_time,
                ];
            }

            $ret[$conf['ConferenceName']] = $conf;
        }

        return $ret;
    }
}
