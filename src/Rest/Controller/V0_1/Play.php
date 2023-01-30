<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller\V0_1;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use function React\Promise\{
    all,
    resolve
};
use RTCKit\Eqivo\Rest\Controller\{
    AuthenticatedTrait,
    ControllerInterface,
};
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\Eqivo\Rest\Inquiry\V0_1\Play as PlayInquiry;

use RTCKit\Eqivo\Rest\Response\AbstractResponse;
use RTCKit\Eqivo\Rest\Response\V0_1\Play as PlayResponse;
use RTCKit\Eqivo\Rest\View\V0_1\Play as PlayView;

use RTCKit\FiCore\Switch\CallLegEnum;

/**
 * @OA\Post(
 *      tags={"Call"},
 *      path="/v0.1/Play/",
 *      summary="/v0.1/Play/",
 *      description="Plays media into a live call",
 *      security={{"basicAuth": {}}},
 *      @OA\RequestBody(
 *          description="POST parameters",
 *          @OA\MediaType(
 *              mediaType="application/x-www-form-urlencoded",
 *              @OA\Schema(ref="#/components/schemas/PlayParameters"),
 *          ),
 *      ),
 *      @OA\Response(
 *          response="200",
 *          description="Response",
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(ref="#/components/schemas/PlayResponse"),
 *          ),
 *      ),
 * )
 */
class Play implements ControllerInterface
{
    use AuthenticatedTrait;

    public const DEFAULT_LENGTH = 3600;

    public const DEFAULT_LEG = CallLegEnum::ALEG;

    public const DEFAULT_DELIMITER = ',';

    protected PlayView $view;

    public function __construct()
    {
        $this->view = new PlayView();
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $response = new PlayResponse();
        $inquiry = PlayInquiry::factory($request);

        return $this->doExecute($request, $response, $inquiry);
    }

    /**
     * Validates API call parameters
     *
     * @param AbstractInquiry $inquiry
     * @param AbstractResponse $response
     */
    public function validate(AbstractInquiry $inquiry, AbstractResponse $response): void
    {
        assert($inquiry instanceof PlayInquiry);
        assert($response instanceof PlayResponse);

        $inquiry->Loop = isset($inquiry->Loop) ? ($inquiry->Loop === 'true') : false;
        $inquiry->Mix = isset($inquiry->Mix) ? ($inquiry->Mix === 'false') : true;

        if (!isset($inquiry->CallUUID)) {
            $response->Message = PlayResponse::MESSAGE_NO_CALLUUID;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->Sounds)) {
            $response->Message = PlayResponse::MESSAGE_NO_SOUNDS;
            $response->Success = false;

            return;
        }

        $inquiry->Legs ??= static::DEFAULT_LEG->value;

        if (!CallLegEnum::tryFrom($inquiry->Legs)) {
            $response->Message = PlayResponse::MESSAGE_INVALID_LEG;
            $response->Success = false;

            return;
        }

        if (!isset($inquiry->Length)) {
            $inquiry->Length = static::DEFAULT_LENGTH;
        } else {
            $inquiry->Length = (int)$inquiry->Length;

            if ($inquiry->Length < 1) {
                $response->Message = PlayResponse::MESSAGE_INVALID_LENGTH;
                $response->Success = false;

                return;
            }
        }

        $inquiry->delimiter ??= static::DEFAULT_DELIMITER;
        $inquiry->soundList = explode($inquiry->delimiter, $inquiry->Sounds);

        if (!isset($inquiry->soundList[0])) {
            $response->Message = PlayResponse::MESSAGE_INVALID_SOUNDS;
            $response->Success = false;
        }

        /** @psalm-suppress PropertyTypeCoercion */
        array_walk($inquiry->soundList, function (string &$entry) {
            if (strpos($entry, 'http_cache://') === 0) {
                $entry = str_replace(' ', '%20', $entry);
            }
        });

        $channel = $this->app->getChannel($inquiry->CallUUID);

        if (!isset($channel)) {
            $response->Message = PlayResponse::MESSAGE_NOT_FOUND;
            $response->Success = false;

            return;
        }

        $inquiry->channel = $channel;
    }
}
