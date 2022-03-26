<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller;

use RTCKit\Eqivo\Core;
use RTCKit\Eqivo\Rest\Inquiry\V0_1\Play as PlayInquiry;
use RTCKit\Eqivo\Rest\Inquiry\V0_1\SchedulePlay as SchedulePlayInquiry;

use React\Promise\PromiseInterface;
use RTCKit\ESL;
use SimpleXMLElement;
use function React\Promise\{
    all,
    resolve
};

trait PlaySoundsTrait
{
    /**
     * Merges `uuid_displace` commands for both legs
     *
     * @param PlayInquiry|SchedulePlayInquiry $inquiry
     *
     * @return PromiseInterface
     */
    protected function getPlayCommands(PlayInquiry|SchedulePlayInquiry $inquiry): PromiseInterface
    {
        $promises = [];

        if (in_array($inquiry->Legs, ['aleg', 'both'])) {
            $promises[] = $this->getPlayCommandsALeg($inquiry);
        }

        if (in_array($inquiry->Legs, ['nleg', 'both'])) {
            $promises[] = $this->getPlayCommandsBLeg($inquiry);
        }

        return all($promises)
            ->then(function (array $results) {
                $ret = [];

                foreach ($results as $commands) {
                    $ret = array_merge($ret, $commands);
                }

                return resolve($ret);
            });
    }

    /**
     * Retrieves `uuid_displace` commands for A-Leg
     *
     * @param PlayInquiry|SchedulePlayInquiry $inquiry
     *
     * @return PromiseInterface
     */
    protected function getPlayCommandsALeg(PlayInquiry|SchedulePlayInquiry $inquiry): PromiseInterface
    {
        return $this->getDisplaceMediaList($inquiry->core, $inquiry->CallUUID)
            ->then(function (array $stopList) use ($inquiry): PromiseInterface {
                $ret = [];

                foreach ($stopList as $target) {
                    $ret[] = "uuid_displace {$inquiry->CallUUID} stop {$target}";
                }

                $ret[] = "uuid_displace {$inquiry->CallUUID} start '{$inquiry->playStringALeg}' {$inquiry->Length} {$inquiry->aLegFlags}";

                return resolve($ret);
            });
    }

    /**
     * Retrieves `uuid_displace` commands for B-Leg
     *
     * @param PlayInquiry|SchedulePlayInquiry $inquiry
     *
     * @return PromiseInterface
     */
    protected function getPlayCommandsBLeg(PlayInquiry|SchedulePlayInquiry $inquiry): PromiseInterface
    {
        return $inquiry->core->client->api(
            (new ESL\Request\Api)->setParameters("uuid_getvar {$inquiry->CallUUID} bridge_uuid")
        )
            ->then(function (?ESL\Response\ApiResponse $response = null) use ($inquiry): PromiseInterface {
                $uuid = null;

                if (isset($response)) {
                    $body = $response->getBody();

                    if (isset($body)) {
                        if (($body !== '_undef_') && (strpos($body, '-ERR') !== 0)) {
                            $uuid = $body;
                        }
                    }
                }

                if (!$uuid) {
                    $this->app->restServer->logger->warning('No BLeg found');

                    return resolve([]);
                }

                return $this->getDisplaceMediaList($inquiry->core, $uuid)
                    ->then(function (array $stopList) use ($inquiry, $uuid): PromiseInterface {
                        $ret = [];

                        foreach ($stopList as $target) {
                            $ret[] = "uuid_displace {$uuid} stop {$target}";
                        }

                        $ret[] = "uuid_displace {$uuid} start '{$inquiry->playStringBLeg}' {$inquiry->Length} {$inquiry->bLegFlags}";

                        return resolve($ret);
                    });
            });
    }

    protected function getDisplaceMediaList(Core $core, string $uuid): PromiseInterface
    {
        return $core->client->api(
            (new ESL\Request\Api())->setParameters("uuid_buglist {$uuid}")
        )
            ->then(function (?ESL\Response\ApiResponse $response = null): PromiseInterface {
                $xml = null;

                if (isset($response)) {
                    $body = $response->getBody();

                    if (isset($body)) {
                        $xml = simplexml_load_string($body);
                    }
                }

                if (!$xml) {
                    $this->app->restServer->logger->warning('cannot get displace_media_list: no list');

                    return resolve([]);
                }

                $ret = [];

                foreach ($xml as $node) {
                    if ($node->getName() !== 'media-bug') {
                        continue;
                    }

                    if (!isset($node->function, $node->target)) {
                        continue;
                    }

                    if ((string)$node->function === 'displace') {
                        $ret[] = (string)$node->target;
                    }
                }

                return resolve($ret);
            });
    }
}
