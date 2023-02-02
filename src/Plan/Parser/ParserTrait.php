<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\Parser;

use RTCKit\Eqivo\App;

trait ParserTrait
{
    public App $app;

    public function setApp(App $app): static
    {
        $this->app = $app;

        return $this;
    }
}
