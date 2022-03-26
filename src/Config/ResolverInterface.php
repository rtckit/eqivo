<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Config;

interface ResolverInterface
{
    public function resolve(Set $config): void;
}
