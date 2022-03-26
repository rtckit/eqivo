<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Controller;

use RTCKit\Eqivo\{
    CallRequest,
    Job
};

use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\ESL;
use function React\Promise\resolve;

trait DialerTrait
{
    protected function loopGateways(CallRequest $callRequest): PromiseInterface
    {
        $originateString = array_shift($callRequest->gateways);

        assert(!is_null($originateString));

        return $callRequest->core->client->bgApi((new ESL\Request\BgApi)->setParameters($originateString))
            ->then(function (ESL\Response $response) use ($callRequest): PromiseInterface {
                if ($response instanceof ESL\Response\CommandReply) {
                    $uuid = $response->getHeader('job-uuid');

                    if (isset($uuid)) {
                        $job = new Job;
                        $job->uuid = $uuid;
                        $job->command = 'originate';
                        $job->callRequest = $callRequest;
                        $job->deferred = new Deferred;

                        $callRequest->core->addJob($job);

                        assert(!isset($callRequest->job));

                        $callRequest->job = $job;

                        $this->app->restServer->logger->debug("Waiting Call attempt for RequestUUID {$callRequest->uuid} ...");

                        return $job->deferred->promise()
                            ->then(function (bool $success) use ($callRequest): PromiseInterface {
                                unset($callRequest->job);

                                if ($success) {
                                    $this->app->restServer->logger->info("Call Attempt OK for RequestUUID {$callRequest->uuid}");

                                    return resolve();
                                }

                                $this->app->restServer->logger->info("Call Attempt Failed for RequestUUID {$callRequest->uuid}, retrying next gateway ...");

                                return $this->loopGateways($callRequest);
                            });
                    }
                }

                $this->app->restServer->logger->error("Call Failed for RequestUUID {$callRequest->uuid} -- JobUUID not received");

                return $this->loopGateways($callRequest);
            })
            ->otherwise(function (\Throwable $t) {
                $t = $t->getPrevious() ?: $t;

                $this->app->inboundServer->logger->error('loopGateways exception: ' . $t->getMessage(), [
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ]);
            });
    }
}
