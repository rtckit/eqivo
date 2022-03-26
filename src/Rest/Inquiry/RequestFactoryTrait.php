<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry;

use RTCKit\Eqivo\Exception\BadRequestException;

use Psr\Http\Message\ServerRequestInterface;

trait RequestFactoryTrait
{
    final public function __construct() {}

    public static function factory(ServerRequestInterface $request): static
    {
        /**
         * > Cannot safely instantiate class ... with "new static" as its constructor might change in child classes
         * Not really a concern as the constructor is declared `final`
         *
         * @psalm-suppress UnsafeInstantiation
         */
        $ret = new static;
        $params = $request->getParsedBody();

        if (is_null($params)) {
            return $ret;
        }

        if (!is_array($params)) {
            throw new BadRequestException;
        }

        $vars = get_class_vars(static::class);

        foreach ($vars as $key => $value) {
            if (isset($params[$key])) {
                $ret->{$key} = $params[$key];
            }
        }

        return $ret;
    }
}
