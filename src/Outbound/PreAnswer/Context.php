<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound\PreAnswer;

use RTCKit\Eqivo\Session;
use RTCKit\Eqivo\Outbound\RestXmlElement;
use RTCKit\Eqivo\Outbound\ContextInterface;

class Context implements ContextInterface
{
    public Session $session;

    public RestXmlElement $origRestXml;

    public string $origElement;
}
