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
 *     schema="SendDigitsParameters",
 *     required={"CallUUID", "Digits"},
 * )
 */
class SendDigits
{
    use RequestFactoryTrait;

    /**
     * @OA\Property(
     *     description="Unique identifier of the call to send DTMF to",
     *     example="d4cd08fe-4245-490a-ae39-5b58c6addbe8",
     * )
     */
    public string $CallUUID;

    /**
     * @OA\Property(
     *     description="DTMF tones to be sent; each occurrence of `w` implies a 0.5 seconds delay whereas `W` will apply a whole second delay. To alter the tone duration (by default, 2000ms), append `@` and the length in milliseconds at the end of the string",
     *     example="1w2w3W#*@1500",
     * )
     */
    public string $Digits;

    /**
     * @OA\Property(
     *     description="Call leg(s) to which DTMFs will be sent; `aleg` refers to the initial call leg, `bleg` refers to the bridged call leg, if applicable.",
     *     example="both",
     *     enum={"aleg", "bleg", "both"},
     *     default=RTCKit\Eqivo\Rest\Controller\V0_1\SendDigits::DEFAULT_LEG,
     * )
     */
    public string $Leg;

    public Session $session;

    public Core $core;
}
