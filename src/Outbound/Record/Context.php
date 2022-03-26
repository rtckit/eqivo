<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\Record;

use RTCKit\Eqivo\Session;
use RTCKit\Eqivo\Outbound\ContextInterface;

use stdClass as Event;

class Context implements ContextInterface
{
    public Session $session;
    public int $maxLength;
    public int $silenceThreshold;
    public int $timeout;
    public string $finishOnKey;
    public string $filePath;
    public bool $playBeep;
    public string $fileFormat;
    public string $fileName;
    public bool $bothLegs;
    public bool $redirect;
    public string $action;
    public string $method;
    public string $recordFile;
    public Event $event;
}
