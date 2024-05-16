<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\Parser;

use React\Promise\PromiseInterface;
use function React\Promise\{
    all,
    resolve,
};
use RTCKit\Eqivo\Exception\RestXmlAttributeException;
use RTCKit\Eqivo\Plan\Dial\{
    Element as DialElement,
    Number,
};
use RTCKit\Eqivo\Plan\Producer;
use RTCKit\Eqivo\Plan\RestXmlElement;
use RTCKit\FiCore\Plan\Playback\Element as PlaybackElement;
use RTCKit\FiCore\Plan\Silence\Element as SilenceElement;
use RTCKit\FiCore\Plan\Speak\Element as SpeakElement;

use RTCKit\FiCore\Switch\Channel;

class Dial implements ParserInterface
{
    use ParserTrait;

    /** @var string */
    public const ELEMENT_TYPE = 'Dial';

    /** @var bool */
    public const NO_ANSWER = true;

    /** @var list<string> */
    public const NESTABLES = ['Number'];

    /** @var int */
    public const DEFAULT_TIMELIMIT = 14400;

    /** @var int */
    public const DEFAULT_TIMEOUT = -1;

    public function parse(RestXmlElement $xmlElement, Channel $channel): PromiseInterface
    {
        $element = new DialElement();
        $element->channel = $channel;

        $attributes = $xmlElement->attributes();

        if (isset($attributes->method)) {
            if (!in_array((string)$attributes->method, ['GET', 'POST'])) {
                throw new RestXmlAttributeException("method must be 'GET' or 'POST'");
            }

            $method = (string)$attributes->method;
        } else {
            $method = 'POST';
        }

        if (isset($attributes->action)) {
            $element->onHangup = $method . ':' . (string)$attributes->action;
        }

        $element->callerId = isset($attributes->callerId) ? (string)$attributes->callerId : '';
        $element->callerName = isset($attributes->callerName) ? (string)$attributes->callerName : '';

        if (isset($attributes->timeLimit)) {
            $element->timeLimit = (int)$attributes->timeLimit;

            if ($element->timeLimit < 1) {
                $element->timeLimit = self::DEFAULT_TIMELIMIT;
            }
        } else {
            $element->timeLimit = self::DEFAULT_TIMELIMIT;
        }

        if (isset($attributes->timeout)) {
            $element->timeout = (int)$attributes->timeout;

            if ($element->timeout < 1) {
                $element->timeout = self::DEFAULT_TIMEOUT;
            }
        } else {
            $element->timeout = self::DEFAULT_TIMEOUT;
        }

        $confirmSound = isset($attributes->confirmSound) ? (string)$attributes->confirmSound : '';
        $element->confirmKey = isset($attributes->confirmKey) ? (string)$attributes->confirmKey : '';
        $dialMusic = isset($attributes->dialMusic) ? (string)$attributes->dialMusic : '';
        $element->hangupOnStar = isset($attributes->hangupOnStar) ? ((string)$attributes->hangupOnStar === 'true') : false;
        $element->redirect = isset($attributes->redirect) ? ((string)$attributes->redirect === 'true') : true;

        if (isset($attributes->callbackMethod)) {
            if (!in_array((string)$attributes->callbackMethod, ['GET', 'POST'])) {
                throw new RestXmlAttributeException("callbackMethod must be 'GET' or 'POST'");
            }

            $method = (string)$attributes->callbackMethod;
        } else {
            $method = 'POST';
        }

        if (isset($attributes->callbackUrl)) {
            $element->signalAttn = "{$method}:" . (string)$attributes->callbackUrl;
        }

        $element->digitsMatch = isset($attributes->digitsMatch) ? (string)$attributes->digitsMatch : '';

        $numDialStr = [];

        foreach ($xmlElement as $number) {
            $tuples = [];
            $entry = new Number();
            $entry->number = str_replace([',', '|'], '', trim((string)$number));

            if (!isset($entry->number[0])) {
                $this->app->planConsumer->logger->error('Number not defined on Number object');

                continue;
            }

            $attributes = $number->attributes();

            $entry->extraDialString = isset($attributes->extraDialString) ? (string)$attributes->extraDialString : '';
            $entry->sendDigits = isset($attributes->sendDigits) ? (string)$attributes->sendDigits : '';
            $entry->sendOnPreanswer = isset($attributes->sendOnPreanswer) ? ((string)$attributes->sendOnPreanswer === 'true') : true;
            $entry->gateways = explode(',', isset($attributes->gateways) ? (string)$attributes->gateways : '');
            $entry->gatewayCodecs = str_getcsv(isset($attributes->gatewayCodecs) ? (string)$attributes->gatewayCodecs : '', ',', "'");
            $entry->gatewayTimeouts = explode(',', isset($attributes->gatewayTimeouts) ? (string)$attributes->gatewayTimeouts : '');
            $entry->gatewayRetries = explode(',', isset($attributes->gatewayRetries) ? (string)$attributes->gatewayRetries : '');

            if (!isset($entry->gateways[0][0])) {
                $this->app->planConsumer->logger->error('Gateway not defined on Number object');

                continue;
            }

            $optionSendDigits = '';

            if (isset($entry->sendDigits[0])) {
                if ($entry->sendOnPreanswer) {
                    $optionSendDigits = "api_on_media='uuid_recv_dtmf \${uuid} {$entry->sendDigits}'";
                } else {
                    $optionSendDigits = "api_on_answer_2='uuid_recv_dtmf \${uuid} {$entry->sendDigits}'";
                }
            }

            foreach ($entry->gateways as $idx => $gateway) {
                $numOptions = [];

                if (isset($element->signalAttn)) {
                    $numOptions[] = $this->app->config->appPrefix . '_dial_aleg=' . $element->channel->uuid;
                    $numOptions[] = $this->app->config->appPrefix . '_dial_signal_attn=' . $element->signalAttn;
                }

                if (isset($optionSendDigits[0])) {
                    $numOptions[] = $optionSendDigits;
                }

                if (isset($entry->gatewayCodecs[$idx][0])) {
                    $numOptions[] = "absolute_codec_string='{$entry->gatewayCodecs[$idx]}'";
                }

                if (isset($entry->gatewayTimeouts[$idx]) && ($entry->gatewayTimeouts[$idx] > 0)) {
                    $numOptions[] = 'leg_timeout=' . $entry->gatewayTimeouts[$idx];
                }

                if (!isset($entry->gatewayRetries[$idx]) || ((int)$entry->gatewayRetries[$idx] < 1)) {
                    $entry->gatewayRetries[$idx] = 1;
                }

                if (isset($entry->extraDialString[0])) {
                    $numOptions[] = $entry->extraDialString;
                }

                $options = '';

                if (isset($numOptions[0])) {
                    $options = '[' . implode(',', $numOptions) . ']';
                }

                $numStr = "{$options}{$gateway}{$entry->number}";

                if ((int)$entry->gatewayRetries[$idx] === 1) {
                    $tuples[] = $numStr;
                } else {
                    $tuples[] = $numStr . str_repeat('|' . $numStr, (int)$entry->gatewayRetries[$idx] - 1);
                }
            }

            $entry->dialStr = implode('|', $tuples);
            $numDialStr[] = $entry->dialStr;
            $element->numbers[] = $entry;
        }

        $element->dialStr = implode(':_:', $numDialStr);

        assert($this->app->planProducer instanceof Producer);

        $promises = [];

        if (!empty($confirmSound)) {
            $promises['confirmSounds'] = $this->app->planProducer->fetchRemotePlaybackArray(
                $element,
                $this->app->restServer->config->defaultHttpMethod . ':' . $confirmSound,
                [PlaybackElement::class, SpeakElement::class, SilenceElement::class]
            );
        }

        if (!isset($dialMusic[0])) {
            $element->setVars[] = 'bridge_early_media=false';
            $element->setVars[] = 'instant_ringback=true';
            $element->setVars[] = 'ringback=${us-ring}';
        } elseif ($dialMusic === 'none') {
            $element->setVars[] = 'bridge_early_media=false';
            $element->unsetVars[] = 'instant_ringback';
            $element->unsetVars[] = 'ringback';
        } elseif ($dialMusic === 'real') {
            $element->setVars[] = 'bridge_early_media=false';
            $element->setVars[] = 'instant_ringback=false';
            $element->unsetVars[] = 'ringback';
        } else {
            $promises['dialMusic'] = $this->app->planProducer->fetchRemotePlaybackArray(
                $element,
                $this->app->restServer->config->defaultHttpMethod . ':' . $dialMusic,
                [PlaybackElement::class, SpeakElement::class, SilenceElement::class]
            );
        }

        return all($promises)
            ->then(function (array $playbackArray) use ($element) {
                if (!empty($playbackArray['confirmSounds'])) {
                    /** @phpstan-ignore-next-line */
                    $element->confirmSounds = $playbackArray['confirmSounds'];
                }

                if (!empty($playbackArray['dialMusic'])) {
                    $element->dialMusic = $playbackArray['dialMusic'];
                }

                return $element;
            });
    }
}
