<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\Parser;

use React\Promise\PromiseInterface;
use RTCKit\Eqivo\Exception\RestXmlFormatException;
use RTCKit\Eqivo\Plan\Producer;
use RTCKit\Eqivo\Plan\RestXmlElement;
use RTCKit\FiCore\Plan\Conference\Element as ConferenceElement;
use RTCKit\FiCore\Plan\Playback\Element as PlaybackElement;
use RTCKit\FiCore\Plan\Silence\Element as SilenceElement;
use RTCKit\FiCore\Switch\Channel;

class Conference implements ParserInterface
{
    use ParserTrait;

    /** @var string */
    public const ELEMENT_TYPE = 'Conference';

    public const DEFAULT_TIMELIMIT = 0;

    public const DEFAULT_MAXMEMBERS = 200;

    public const RECORD_FILE_FORMATS = ['wav', 'mp3'];

    public const ALLOWED_METHODS = ['GET', 'POST'];

    public function parse(RestXmlElement $xmlElement, Channel $channel): PromiseInterface
    {
        $element = new ConferenceElement();

        $element->channel = $channel;
        $element->room = trim((string)$xmlElement);

        if (!strlen($element->room)) {
            throw new RestXmlFormatException('Conference Room must be defined');
        }

        $attributes = $xmlElement->attributes();
        $element->fqrn = $element->room;

        if (strpos($element->fqrn, '@') === false) {
            $element->fqrn = "{$element->room}@{$this->app->config->appPrefix}";
        }

        $mohSound = (isset($attributes->waitSound) && !empty((string)$attributes->waitSound))
            ? $this->app->restServer->config->defaultHttpMethod . ':' . (string)$attributes->waitSound
            : '';

        if (isset($attributes->hangupOnStar) && ((string)$attributes->hangupOnStar === 'true')) {
            $element->terminator = '*';
        }

        $element->maxDuration = self::DEFAULT_TIMELIMIT;

        if (isset($attributes->timeLimit)) {
            $element->maxDuration = (int)$attributes->timeLimit;
        }

        if ($element->maxDuration <= 0) {
            $element->maxDuration = self::DEFAULT_TIMELIMIT;
        }

        $element->maxMembers = self::DEFAULT_MAXMEMBERS;

        if (isset($attributes->maxMembers)) {
            $element->maxMembers = (int)$attributes->maxMembers;
        }

        if (($element->maxMembers <= 0) || ($element->maxMembers > self::DEFAULT_MAXMEMBERS)) {
            $element->maxMembers = self::DEFAULT_MAXMEMBERS;
        }

        $enterMedium = isset($attributes->enterSound) ? (string)$attributes->enterSound : '';

        switch ($enterMedium) {
            case 'beep:1':
                $element->enterMedium = 'tone_stream://%%(300,200,700)';
                break;

            case 'beep:2':
                $element->enterMedium = 'tone_stream://L=2;%%(300,200,700)';
                break;
        }

        $leaveMedium = isset($attributes->exitSound) ? (string)$attributes->exitSound : '';

        switch ($leaveMedium) {
            case 'beep:1':
                $element->leaveMedium = 'tone_stream://%%(300,200,700)';
                break;

            case 'beep:2':
                $element->leaveMedium = 'tone_stream://L=2;%%(300,200,700)';
                break;
        }

        $filePath = isset($attributes->recordFilePath) ? (string)$attributes->recordFilePath : '';
        $fileFormat = isset($attributes->recordFileFormat) ? (string)$attributes->recordFileFormat : 'mp3';

        if (!in_array($fileFormat, self::RECORD_FILE_FORMATS)) {
            throw new RestXmlFormatException("Format must be '" . implode("', '", self::RECORD_FILE_FORMATS) . "'");
        }

        $fileName = isset($attributes->recordFileName) ? (string)$attributes->recordFileName : '';

        if (!empty($filePath)) {
            $filePath = rtrim($filePath, '/') . '/';
            $fileName = !empty($fileName) ? $fileName : date('Ymd-His') . '_' . $channel->uuid;
            $element->medium = "{$filePath}{$fileName}.{$fileFormat}";
        }

        $method = isset($attributes->method) ? (string)$attributes->method : 'POST';

        if (isset($attributes->method) && !in_array($method, self::ALLOWED_METHODS)) {
            throw new RestXmlFormatException("method must be '" . implode("', '", self::ALLOWED_METHODS) . "'");
        }

        $element->sequence = isset($attributes->action) ? $method . ':' . (string)$attributes->action : '';

        $callbackUrl = isset($attributes->callbackUrl) ? (string)$attributes->callbackUrl : '';
        $callbackMethod = isset($attributes->callbackMethod) ? (string)$attributes->callbackMethod : 'POST';

        if (!in_array($callbackMethod, self::ALLOWED_METHODS)) {
            throw new RestXmlFormatException("callbackMethod must be '" . implode("', '", self::ALLOWED_METHODS) . "'");
        }

        if (!empty($callbackUrl)) {
            $signalAttn = "{$callbackMethod}:{$callbackUrl}";

            $element->onDtmfAttn = $element->onEnterAttn = $element->onLeaveAttn = $signalAttn;

            if (isset($attributes->floorEvent) && ((string)$attributes->floorEvent === 'true')) {
                $element->onFloorAttn = $signalAttn;
            }
        }

        $element->matchTones = isset($attributes->digitsMatch) ? (string)$attributes->digitsMatch : '';

        $muted = isset($attributes->muted) ? ((string)$attributes->muted === 'true') : false;
        $startOnEnter = isset($attributes->startConferenceOnEnter) ? ((string)$attributes->startConferenceOnEnter === 'true') : true;
        $endOnExit = isset($attributes->endConferenceOnExit) ? ((string)$attributes->endConferenceOnExit === 'true') : false;
        $stayAlone = isset($attributes->stayAlone) ? ((string)$attributes->stayAlone === 'true') : true;

        if ($muted) {
            $element->flags[] = 'mute';
        }

        if ($startOnEnter) {
            $element->flags[] = 'moderator';
        }

        if (!$stayAlone) {
            $element->flags[] = 'mintwo';
        }

        if ($endOnExit) {
            $element->flags[] = 'endconf';
        }

        assert($this->app->planProducer instanceof Producer);

        return $this->app->planProducer->fetchRemotePlaybackArray($element, $mohSound, [PlaybackElement::class, SilenceElement::class])
            ->then(function (array $moh) use ($element): ConferenceElement {
                /** @var list<string> $moh */
                $element->mohMedia = $moh;

                return $element;
            });
    }
}
