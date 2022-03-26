<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\Eqivo\Rest\Inquiry\RequestFactoryTrait;

/**
 * @OA\Schema(
 *     schema="HangupAllCallsParameters",
 * )
 */
class HangupAllCalls
{
    use RequestFactoryTrait;
}
