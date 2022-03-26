<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Outbound;

use RTCKit\Eqivo\App;

trait HandlerTrait
{
    public App $app;

    public function setApp(App $app): static
    {
        $this->app = $app;

        return $this;
    }
}
