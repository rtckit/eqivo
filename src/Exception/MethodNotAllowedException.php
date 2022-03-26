<?php
/**
 * RTCKit\Eqivo\Exception\MethodNotAllowedException Class
 */
declare(strict_types = 1);

namespace RTCKit\Eqivo\Exception;

/**
 * Generic method not allowed exception
 */
class MethodNotAllowedException extends EqivoException
{
    /** @var int */
    protected $code = 405;
}
