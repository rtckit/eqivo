<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan\Parser;

use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use RTCKit\Eqivo\Exception\RestXmlFormatException;
use RTCKit\Eqivo\Plan\RestXmlElement;

use RTCKit\FiCore\Plan\Record\Element as RecordElement;

use RTCKit\FiCore\Switch\Channel;

class Record implements ParserInterface
{
    use ParserTrait;

    /** @var string */
    public const ELEMENT_TYPE = 'Record';

    /** @var list<string> */
    public const RECORD_FILE_FORMATS = ['wav', 'mp3'];

    /** @var string */
    public const DEFAULT_RECORD_FORMAT = 'mp3';

    /** @var list<string> */
    public const ALLOWED_METHODS = ['GET', 'POST'];

    public function parse(RestXmlElement $xmlElement, Channel $channel): PromiseInterface
    {
        $element = new RecordElement();
        $element->channel = $channel;

        $attributes = $xmlElement->attributes();

        if (isset($attributes->maxLength)) {
            $element->maxDuration = (int)$attributes->maxLength;

            if ($element->maxDuration < 1) {
                throw new RestXmlFormatException("Record 'maxLength' must be a positive integer");
            }
        } else {
            $element->maxDuration = 60;
        }

        /* silenceThreshold was not originally exposed in RestXML, now it is.*/
        $element->silenceThreshold = isset($attributes->silenceThreshold) ? (int)$attributes->silenceThreshold : 500;

        if (isset($attributes->timeout)) {
            $element->silenceHits = (int)$attributes->timeout;

            if ($element->silenceHits < 1) {
                throw new RestXmlFormatException("Record 'timeout' must be a positive integer");
            }
        } else {
            $element->silenceHits = 15;
        }

        $element->terminators = isset($attributes->finishOnKey) ? (string)$attributes->finishOnKey : '1234567890*#';

        if (isset($attributes->playBeep) && ((string)$attributes->playBeep === 'true')) {
            $element->playMedium = 'tone_stream://%(300,200,700)';
        }

        $filePath = isset($attributes->filePath) ? (rtrim((string)$attributes->filePath, '/') . '/') : '';
        $fileName = isset($attributes->fileName) ? (string)$attributes->fileName : '';
        $fileFormat = isset($attributes->fileFormat) ? (string)$attributes->fileFormat : static::DEFAULT_RECORD_FORMAT;

        if (!in_array($fileFormat, static::RECORD_FILE_FORMATS)) {
            throw new RestXmlFormatException("Format must be '" . implode("', '", static::RECORD_FILE_FORMATS) . "'");
        }

        if (!strlen($fileName)) {
            $fileName = date('Ymd-His') . '_' . $channel->uuid;
        }

        $element->medium = "{$filePath}{$fileName}.{$fileFormat}";

        $element->async = isset($attributes->bothLegs) ? ((string)$attributes->bothLegs === 'true') : false;
        $redirect = isset($attributes->redirect) ? ((string)$attributes->redirect === 'true') : true;

        $method = isset($attributes->method) ? (string)$attributes->method : 'POST';

        if (isset($attributes->method) && !in_array($method, static::ALLOWED_METHODS)) {
            throw new RestXmlFormatException("method must be '" . implode("', '", static::ALLOWED_METHODS) . "'");
        }

        if (isset($attributes->action) && !empty((string)$attributes->action)) {
            if ($redirect) {
                $element->onCompletedSeq = $method . ':' . (string)$attributes->action;
            } else {
                $element->onCompletedAttn = $method . ':' . (string)$attributes->action;
            }
        }

        return resolve($element);
    }
}
