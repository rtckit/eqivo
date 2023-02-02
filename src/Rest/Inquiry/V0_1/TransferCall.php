<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\FiCore\Command\Channel\Redirect;
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;

use RTCKit\FiCore\Switch\{
    Channel,
    Core
};

/**
 * @OA\Schema(
 *     schema="TransferCallParameters",
 *     required={"CallUUID", "Url"},
 * )
 */
class TransferCall extends AbstractInquiry
{
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

    public Channel $channel;

    public string $defaultHttpMethod;

    public function export(): Redirect\Request
    {
        $request = new Redirect\Request();

        $request->action = Redirect\ActionEnum::Redirect;
        $request->channel = $this->channel;
        $request->sequence = "{$this->defaultHttpMethod}:{$this->Url}";

        return $request;
    }
}
