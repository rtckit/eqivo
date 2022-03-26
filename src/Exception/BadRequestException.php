<?php
/**
 * RTCKit\Eqivo\Exception\BadRequestException Class
 */
declare(strict_types = 1);

namespace RTCKit\Eqivo\Exception;

/**
 * Generic bad request exception
 */
class BadRequestException extends EqivoException
{
    /** @var int */
    protected $code = 400;
}
