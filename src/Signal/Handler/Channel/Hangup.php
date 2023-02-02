<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Signal\Handler\Channel;

use RTCKit\Eqivo\Signal\Handler\AbstractHandler;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Signal\Channel\Hangup as HangupSignal;
use RTCKit\FiCore\Switch\{
    HangupCauseEnum,
    StatusEnum,
};
use RTCKit\SIP\Exception\SIPException;
use RTCKit\SIP\Header\NameAddrHeader;

class Hangup extends AbstractHandler
{
    public function export(AbstractSignal $signal): array
    {
        assert($signal instanceof HangupSignal);

        $callerNum = null;
        $direction = null;

        $payload = [
            'RestApiServer' => $this->getRestServerAdvertisedHost(),
            'CallUUID' => isset($signal->channel) ? $signal->channel->uuid : ($signal->event->{'Unique-ID'} ?? ''),
            'To' => '',
        ];

        if (isset($signal->originateJob)) {
            if (isset($signal->originateJob->tags['accountsid'])) {
                $payload['AccountSID'] = $signal->originateJob->tags['accountsid'];
            }

            $payload['To'] = ltrim($signal->originateJob->destination, '+');
            $callerNum = ltrim($signal->originateJob->source, '+');

            $direction = 'outbound';

            if (!isset($signal->attn)) {
                $this->app->signalProducer->logger->debug("No Hangup Dequence for Outgoing Outgoing Call {$payload['CallUUID']}, RequestUUID {$signal->originateJob->uuid}");
            }

            $aLegRequestUuidVar = "variable_{$this->app->config->appPrefix}_request_uuid";
            $schedHangupIdVar = "variable_{$this->app->config->appPrefix}_sched_hangup_id";
            $payload['RequestUUID'] = $signal->originateJob->uuid;

            if (isset($signal->event->variable_sip_h_Diversion)) {
                try {
                    $diversion = NameAddrHeader::parse([(string)$signal->event->variable_sip_h_Diversion]);

                    if (isset($diversion->uri, $diversion->uri->user)) {
                        $payload['ForwardedFrom'] = ltrim($diversion->uri->user, '+');
                    }
                } catch (SIPException $e) {
                    $this->app->signalProducer->logger->error("Cannot parse Diversion SIP header '{$signal->event->variable_sip_h_Diversion}'");
                }
            }

            if (isset($signal->event->{'Caller-Unique-ID'}, $signal->event->{'Caller-Unique-ID'}[0])) {
                $payload['ALegUUID'] = $signal->event->{'Caller-Unique-ID'};
            }

            if (isset($signal->event->{$aLegRequestUuidVar}, $signal->event->{$aLegRequestUuidVar}[0])) {
                $payload['ALegRequestUUID'] = $signal->event->{$aLegRequestUuidVar};
            }

            if (isset($signal->event->{$schedHangupIdVar}, $signal->event->{$schedHangupIdVar}[0])) {
                $payload['ScheduledHangupId'] = $signal->event->{$schedHangupIdVar};
            }
        } elseif (isset($signal->channel)) {
            $hangupSigVar = "variable_{$this->app->config->appPrefix}_hangup_attn";

            if (isset($signal->event->{$hangupSigVar})) {
                $signal->attn = $signal->event->{$hangupSigVar};

                $this->app->signalProducer->logger->debug("Using Hangup Sequence for CallUUID {$signal->channel->uuid}");
            } else {
                $answerUrlVar = "variable_{$this->app->config->appPrefix}_answer_seq";

                if (isset($this->app->config->defaultHangupSequence)) {
                    $signal->attn = $this->app->config->defaultHangupSequence;

                    $this->app->signalProducer->logger->debug("Using Hangup Sequence from DefaultHangupSequence for CallUUID {$signal->channel->uuid}");
                } elseif (isset($signal->event->{$answerUrlVar})) {
                    $signal->attn = $signal->event->{$answerUrlVar};

                    $this->app->signalProducer->logger->debug("Using Hangup Sequence from AnswerSequence for CallUUID {$signal->channel->uuid}");
                } elseif (isset($this->app->config->defaultAnswerUrl)) {
                    $signal->attn = $this->app->config->defaultAnswerUrl;

                    $this->app->signalProducer->logger->debug("Using Hangup Sequence from DefaultAnswerSequence for CallUUID {$signal->channel->uuid}");
                }
            }

            if (!isset($signal->attn)) {
                $this->app->signalProducer->logger->debug("No Hangup Sequence for Incoming CallUUID {$signal->channel->uuid}");
            }

            if (isset($signal->channel->tags['accountsid'])) {
                $payload['AccountSID'] = $signal->channel->tags['accountsid'];
            }

            $calledNumVar = "variable_{$this->app->config->appPrefix}_destination_number";

            if (!isset($signal->event->{$calledNumVar}) || ($signal->event->{$calledNumVar} === '_undef_')) {
                $payload['To'] = isset($signal->event->{'Caller-Destination-Number'}) ? $signal->event->{'Caller-Destination-Number'} : '';
            } else {
                $payload['To'] = $signal->event->{$calledNumVar};
            }

            $payload['To'] = ltrim($payload['To'], '+');
            $callerNum = isset($signal->event->{'Caller-Caller-ID-Number'}) ? $signal->event->{'Caller-Caller-ID-Number'} : '';
            $direction = isset($signal->event->{'Call-Direction'}) ? $signal->event->{'Call-Direction'} : '';

            unset($signal->channel);
        }

        if (isset($signal->attn)) {
            $sipUriVar = "variable_{$this->app->config->appPrefix}_sip_transfer_uri";

            if (isset($signal->event->{$sipUriVar})) {
                $payload['SIPTransfer'] = 'true';
                $payload['SIPTransferURI'] = $signal->event->{$sipUriVar};
            }

            $payload['HangupCause'] = isset($signal->reason) ? $signal->reason->value : HangupCauseEnum::NONE->value;
            $payload['From'] = $callerNum ?? '';
            $payload['Direction'] = $direction ?? '';
            $payload['CallStatus'] = StatusEnum::Completed->value;
        }

        return $payload;
    }
}
