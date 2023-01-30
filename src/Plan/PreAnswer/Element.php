<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\PreAnswer;

use RTCKit\FiCore\Plan\AbstractElement;

class Element extends AbstractElement
{
    /** @var list<AbstractElement> */
    public array $origElements;

    public AbstractElement $origElement;

    /** @var list<AbstractElement> */
    public array $elements;
}
