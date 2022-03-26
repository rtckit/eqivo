<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\Eqivo;
use RTCKit\Eqivo\Rest\Inquiry\RequestFactoryTrait;

/**
 * @OA\Schema(
 *     schema="HangupCallParameters",
 * )
 */
class HangupCall
{
    use RequestFactoryTrait;

    /**
     * @OA\Property(
     *     description="Unique identifier of the call (when established); this parameter is mutually exclusive with RequestUUID",
     *     example="b0519011-6987-47c8-9270-a820e0978acd",
     * )
     */
    public string $CallUUID;

    /**
     * @OA\Property(
     *     description="Unique identifier of the API request (when the call is not established yet); this parameter is mutually exclusive with CallUUID",
     *     example="c059b96b-04d8-414b-920c-7b373bff916e",
     * )
     */
    public string $RequestUUID;
}
