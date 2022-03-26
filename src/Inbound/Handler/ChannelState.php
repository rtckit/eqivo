<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Inbound\Handler;

use RTCKit\Eqivo\{
    Core,
    EventEnum,
    ChannelStateEnum
};

use RTCKit\ESL;
use stdClass as Event;

class ChannelState implements HandlerInterface
{
    use HandlerTrait;

    /** @var EventEnum */
    public const EVENT = EventEnum::CHANNEL_STATE;

    public function execute(Core $core, Event $event): void
    {
        switch ($event->{'Channel-State'}) {
            case ChannelStateEnum::CS_RESET->value:
                $session = $core->getSession($event->{'Unique-ID'});

                if (!isset($session, $session->transferInProgress)) {
                    return;
                }

                unset($session->transferInProgress);

                $this->app->inboundServer->logger->info('TransferCall In Progress for ' . $session->uuid);

                $core->client->api(
                    (new ESL\Request\Api)->setParameters("uuid_setvar {$session->uuid} {$this->app->config->appPrefix}_transfer_progress false")
                )
                    ->then(function () use ($core, $session) {
                        return $core->client->api(
                            (new ESL\Request\Api)
                                ->setParameters("uuid_transfer {$session->uuid} 'socket:{$this->app->config->outboundServerAdvertisedIp}:{$this->app->config->outboundServerAdvertisedPort} async full' inline")
                        );
                    })
                    ->then(function (?ESL\Response\ApiResponse $response = null) use ($session) {
                        if (!isset($response) || !$response->isSuccessful()) {
                            $body = isset($response) ? ($response->getBody() ?? '') : '';

                            $this->app->inboundServer->logger->info('TransferCall Failed for ' . $session->uuid . ' ' . $body);
                        } else {
                            $this->app->inboundServer->logger->info('TransferCall Done for ' . $session->uuid);
                        }
                    });
                break;

            case ChannelStateEnum::CS_HANGUP->value:
                $session = $core->getSession($event->{'Unique-ID'});

                if (!isset($session, $session->transferInProgress)) {
                    return;
                }

                unset($session->transferInProgress);

                $this->app->inboundServer->logger->warning('TransferCall Aborted (hangup) for ' . $session->uuid);

                break;
        }
    }
}
