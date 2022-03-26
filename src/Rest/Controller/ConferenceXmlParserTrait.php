<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller;

use RTCKit\Eqivo\Core;

use SimpleXMLElement;

trait ConferenceXmlParserTrait
{
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

                $memAttrs = $member->attributes();
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
