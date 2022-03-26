<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\Conference;

use RTCKit\Eqivo\Outbound\ContextInterface;
use RTCKit\Eqivo\Session;

class Context implements ContextInterface
{
    public Session $session;
    public string $room;
    public string $fullRoom;
    public string $uuid;
    public string $mohSound;
    public bool $muted;
    public bool $startOnEnter;
    public bool $endOnExit;
    public bool $stayAlone;
    public bool $hangupOnStar;
    public int $timeLimit;
    public int $maxMembers;
    public string $enterSound;
    public string $exitSound;
    public string $recordFilePath;
    public string $recordFileFormat;
    public string $recordFileName;
    public string $recordFile;
    public string $method;
    public string $action;
    public string $callbackUrl;
    public string $callbackMethod;
    public string $digitsMatch;
    public bool $floorEvent;
    public string $flags;

    /** @var list<string> */
    public array $setVars = [];

    /** @var list<string> */
    public array $unsetVars = [];

    public string $confUuid;
    public int $memberId;
    public string $digitRealm;

    /**
     * Return base Conference payload (for notifications)
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return [
            'ConferenceName' => $this->room,
            'ConferenceUUID' => isset($this->confUuid) ? $this->confUuid : '',
            'ConferenceMemberID' => isset($this->memberId) ? $this->memberId : '',
        ];
    }
}
