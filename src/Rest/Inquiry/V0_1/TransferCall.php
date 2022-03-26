<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\Eqivo\{
    Core,
    Session
};
use RTCKit\Eqivo\Rest\Inquiry\RequestFactoryTrait;

/**
 * @OA\Schema(
 *     schema="TransferCallParameters",
 *     required={"CallUUID", "Url"},
 * )
 */
class TransferCall
{
    use RequestFactoryTrait;

    /**
     * @OA\Property(
     *     description="Unique identifier of the call",
     *     example="03694cf6-62b3-4f00-b0fc-6831ddcc2693",
     * )
     */
    public string $CallUUID;

    /**
     * @OA\Property(
     *     description="Absolute URL which will return the updated RestXML flow",
     *     example="https://example.org/restxml/endpoint/",
     * )
     */
    public string $Url;

    public Session $session;

    public Core $core;
}
