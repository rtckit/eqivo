<?php

declare(strict_types=1);

namespace RTCKit\Eqivo;

use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use RTCKit\ESL;
use RTCKit\React\ESL\InboundClient;
use function React\Promise\resolve;

class Core
{
    public App $app;

    public InboundClient $client;

    public string $uuid;

    /** @var array<string, string> Core's global variables */
    public array $vars = [];

    /** @var array<string, Session> */
    protected array $sessions = [];

    /** @var array<string, Conference> */
    protected array $conferences = [];

    /** @var array<string, Job> */
    protected array $jobs = [];

    /** @var array<string, CallRequest> */
    protected array $callRequests = [];

    /** @var array<string, ScheduledHangup> */
    protected array $scheduledHangups = [];

    /** @var array<string, ScheduledPlay> */
    protected array $scheduledPlays = [];

    public function setClient(InboundClient $client): void
    {
        if (isset($this->client)) {
            unset($this->client);
        }

        $this->client = $client;
    }

    public function addSession(Session $session): void
    {
        $this->sessions[$session->uuid] = $session;
        $session->core = $this;
        $session->app = $this->app;

        $this->app->addSession($session);
    }

    public function getSession(string $uuid): ?Session
    {
        return isset($this->sessions[$uuid]) ? $this->sessions[$uuid] : null;
    }

    public function removeSession(string $uuid): void
    {
        if (isset($this->sessions[$uuid])) {
            $this->app->removeSession($uuid);
            unset($this->sessions[$uuid]);
        }
    }

    public function addConference(Conference $conference): void
    {
        $this->conferences[$conference->uuid] = $conference;
        $conference->core = $this;
        $conference->app = $this->app;

        $this->app->addConference($conference);
    }

    public function getConference(string $uuid): ?Conference
    {
        return isset($this->conferences[$uuid]) ? $this->conferences[$uuid] : null;
    }

    public function removeConference(string $uuid): void
    {
        if (isset($this->conferences[$uuid])) {
            $this->app->removeConference($this->conferences[$uuid]->room);
            unset($this->conferences[$uuid]);
        }
    }

    public function addJob(Job $job): void
    {
        $this->jobs[$job->uuid] = $job;
        $job->core = $this;
        $job->app = $this->app;
    }

    public function getJob(string $uuid): ?Job
    {
        return isset($this->jobs[$uuid]) ? $this->jobs[$uuid] : null;
    }

    public function removeJob(string $uuid): void
    {
        if (isset($this->jobs[$uuid])) {
            unset($this->jobs[$uuid]);
        }
    }

    public function addCallRequest(CallRequest $callRequest): void
    {
        $this->callRequests[$callRequest->uuid] = $callRequest;
        $callRequest->core = $this;
        $callRequest->app = $this->app;

        $this->app->addCallRequest($callRequest);
    }

    public function getCallRequest(string $uuid): ?CallRequest
    {
        return isset($this->callRequests[$uuid]) ? $this->callRequests[$uuid] : null;
    }

    public function removeCallRequest(string $uuid): void
    {
        if (isset($this->callRequests[$uuid])) {
            unset($this->callRequests[$uuid]);
            $this->app->removeCallRequest($uuid);
        }
    }

    public function addScheduledHangup(ScheduledHangup $scheduledHangup): void
    {
        $this->scheduledHangups[$scheduledHangup->uuid] = $scheduledHangup;
        $scheduledHangup->core = $this;
        $scheduledHangup->app = $this->app;

        $this->app->addScheduledHangup($scheduledHangup);

        Loop::addTimer($scheduledHangup->timeout + 5, function () use ($scheduledHangup) {
            $this->removeScheduledHangup($scheduledHangup->uuid);
            unset($scheduledHangup);
        });
    }

    public function getScheduledHangup(string $uuid): ?ScheduledHangup
    {
        return isset($this->scheduledHangups[$uuid]) ? $this->scheduledHangups[$uuid] : null;
    }

    public function removeScheduledHangup(string $uuid): void
    {
        if (isset($this->scheduledHangups[$uuid])) {
            unset($this->scheduledHangups[$uuid]);
            $this->app->removeScheduledHangup($uuid);
        }
    }

    public function addScheduledPlay(ScheduledPlay $scheduledPlay): void
    {
        $this->scheduledPlays[$scheduledPlay->uuid] = $scheduledPlay;
        $scheduledPlay->core = $this;
        $scheduledPlay->app = $this->app;

        $this->app->addScheduledPlay($scheduledPlay);

        Loop::addTimer($scheduledPlay->timeout + 5, function () use ($scheduledPlay) {
            $this->removeScheduledPlay($scheduledPlay->uuid);
            unset($scheduledPlay);
        });
    }

    public function getScheduledPlay(string $uuid): ?ScheduledPlay
    {
        return isset($this->scheduledPlays[$uuid]) ? $this->scheduledPlays[$uuid] : null;
    }

    public function removeScheduledPlay(string $uuid): void
    {
        if (isset($this->scheduledPlays[$uuid])) {
            unset($this->scheduledPlays[$uuid]);
            $this->app->removeScheduledPlay($uuid);
        }
    }

    /**
     * Returns core's object count
     *
     * @return array<string, int>
     */
    public function gatherStats(): array
    {
        return [
            'CallRequest' => count($this->callRequests),
            'Conference' => count($this->conferences),
            'Job' => count($this->jobs),
            'ScheduledHangup' => count($this->scheduledHangups),
            'ScheduledPlay' => count($this->scheduledPlays),
            'Session' => count($this->sessions),
        ];
    }
}
