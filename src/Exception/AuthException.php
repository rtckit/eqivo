<?php
/**
 * RTCKit\Eqivo\Exception\AuthException Class
 */
declare(strict_types = 1);

namespace RTCKit\Eqivo\Exception;

/**
 * Generic authentication exception
 */
class AuthException extends EqivoException
{
    /** @var int */
    protected $code = 401;
}
