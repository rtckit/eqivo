<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Command\Channel\SoundTouch;

use React\Promise\PromiseInterface;

use function React\Promise\resolve;
use RTCKit\ESL;
use RTCKit\FiCore\Command\{
    AbstractHandler,
    RequestInterface,
};

use RTCKit\FiCore\Switch\DirectionEnum;

class Handler extends AbstractHandler
{
    public function execute(RequestInterface $request): PromiseInterface
    {
        assert($request instanceof Request);

        $response = new Response();
        $response->successful = true;

        return $request->channel->core->client->api(
            (new ESL\Request\Api())->setParameters("soundtouch {$request->channel->uuid} stop")
        )
            ->then(function (ESL\Response $eslResponse) use ($request, $response): PromiseInterface {
                if ($request->action === ActionEnum::Start) {
                    $command = "soundtouch {$request->channel->uuid} start";

                    if ($request->direction === DirectionEnum::In) {
                        $command .= ' send_leg';
                    }

                    if (isset($request->pitchSemiTones)) {
                        $command .= " {$request->pitchSemiTones}s";
                    }

                    if (isset($request->pitchOctaves)) {
                        $command .= " {$request->pitchOctaves}o";
                    }

                    if (isset($request->pitch)) {
                        $command .= " {$request->pitch}p";
                    }

                    if (isset($request->rate)) {
                        $command .= " {$request->rate}r";
                    }

                    if (isset($request->tempo)) {
                        $command .= " {$request->tempo}t";
                    }

                    return $request->channel->core->client->api((new ESL\Request\Api())->setParameters($command))
                        ->then(function () use ($response): Response {
                            return $response;
                        });
                }

                $response->successful = $response->successful = (bool)$eslResponse->isSuccessful();

                return resolve($response);
            });
    }
}
