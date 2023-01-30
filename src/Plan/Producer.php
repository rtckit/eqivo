<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Plan;

use Psr\Http\Message\ResponseInterface;
use React\Promise\PromiseInterface;
use function React\Promise\{
    all,
    resolve
};

use RTCKit\FiCore\AbstractApp;
use RTCKit\Eqivo\App;
use RTCKit\Eqivo\Exception\{
    RestXmlFormatException,
    RestXmlSyntaxException
};
use RTCKit\Eqivo\Plan\Parser\ParserInterface;
use RTCKit\FiCore\Plan\Playback\Element as PlaybackElement;
use RTCKit\FiCore\Plan\Silence\Element as SilenceElement;
use RTCKit\FiCore\Plan\Speak\Element as SpeakElement;
use RTCKit\FiCore\Plan\{
    AbstractElement,
    AbstractProducer,
};
use RTCKit\FiCore\Signal\AbstractSignal;

use RTCKit\FiCore\Switch\{
    Channel,
    Conference,
};

/**
 * Eqivo Plan Producer (RestXML)
 */
class Producer extends AbstractProducer
{
    protected AbstractApp $app;

    /** @var array<string, ParserInterface> */
    public array $parsers = [];

    public function setApp(AbstractApp $app): static
    {
        $this->app = $app;

        return $this;
    }

    public function setRestXmlElementParser(ParserInterface $parser): Producer
    {
        $this->parsers[$parser::ELEMENT_TYPE] = $parser;

        assert($this->app instanceof App);

        $this->parsers[$parser::ELEMENT_TYPE]->setApp($this->app);

        return $this;
    }

    public function run(): void
    {
        $this->logger = $this->app->createLogger('plan.producer');

        $this->logger->debug('Starting ...');
    }

    /**
     * Exports channel's payload
     *
     * @param Channel $channel
     *
     * @return array<string, mixed>
     */
    public function getChannelPayload(Channel $channel): array
    {
        if (!isset($channel->payload)) {
            $channel->payload = [
                'To' => $channel->to ?? null,
                'Direction' => isset($channel->outbound) ? ($channel->outbound ? 'outbound' : 'inbound') : null,
                'From' => $channel->from ?? null,
                'CallerName' => $channel->callerName ?? null,
                'CallUUID' => $channel->uuid ?? null,
                'CallStatus' => '',
                'CoreUUID' => $channel->coreUuid ?? null,
            ];

            if (isset($channel->tags['accountsid'])) {
                $channel->payload['AccountSID'] = $channel->tags['accountsid'];
            }

            if (isset($channel->aLegUuid, $channel->aLegUuid[0])) {
                $channel->payload['ALegUUID'] = $channel->aLegUuid;
            }

            if (isset($channel->aLegRequestUuid, $channel->aLegRequestUuid[0])) {
                $channel->payload['ALegRequestUUID'] = $channel->aLegRequestUuid;
            }

            if (isset($channel->schedHangupId, $channel->schedHangupId[0])) {
                $channel->payload['ScheduledHangupId'] = $channel->schedHangupId;
            }

            if (isset($channel->forwardedFrom, $channel->forwardedFrom[0])) {
                $channel->payload['ForwardedFrom'] = $channel->forwardedFrom;
            }

            foreach ($channel->app->config->extraChannelVars as $var) {
                $loVar = strtolower($var);

                if (isset($channel->context[$loVar])) {
                    $channel->payload[$var] = $channel->context[$loVar];
                }
            }
        }

        assert(is_array($channel->payload));

        $channel->payload['CallStatus'] = isset($channel->status) ? $channel->status->value : null;

        return $channel->payload;
    }

    /**
     * Exports conference's payload
     *
     * @param Conference $conference
     *
     * @return array<string, mixed>
     */
    public function getConferencePayload(Conference $conference): array
    {
        return [
            'ConferenceName' => $conference->room,
        ];
    }

    /**
     * Fetches remote RestXML
     *
     * @param Channel $channel
     * @param string $sequence
     * @param ?AbstractSignal $signal
     *
     * @throws RestXmlFormatException
     * @throws RestXmlSyntaxException
     *
     * @return PromiseInterface
     */
    public function produce(Channel $channel, string $sequence, ?AbstractSignal $signal = null): PromiseInterface
    {
        $parts = explode(':', $sequence, 2);

        if (!isset($parts[1])) {
            $this->logger->error('Invalid method/URL combination');

            return resolve([]);
        }

        $method = $parts[0];
        $url = $parts[1];
        $params = $this->getChannelPayload($channel);

        if (isset($signal)) {
            $params = array_merge($params, $this->app->signalProducer->export($signal) ?? []);
        }

        assert($this->app instanceof App);

        return $this->app->httpClient->makeRequest($url, $method, $params)
            ->then(function (ResponseInterface $response) use ($method, $url, $channel, $params): PromiseInterface {
                $this->logger->info("Fetched {$method} {$url} with " . json_encode($params));

                $xmlStr = (string)$response->getBody();

                if (!strlen($xmlStr)) {
                    $this->logger->warning('No XML Response');

                    return resolve([]);
                }

                $restXml = simplexml_load_string($xmlStr, RestXmlElement::class);

                if ($restXml === false) {
                    throw new RestXmlSyntaxException('Invalid RESTXML Response Syntax: ' . $xmlStr);
                }

                if ($restXml->getName() !== 'Response') {
                    throw new RestXmlFormatException('No Response Tag Present');
                }

                return $this->parseElements($restXml, $channel);
            })
            ->otherwise(function (\Throwable $t) {
                $t = $t->getPrevious() ?: $t;

                $this->logger->error('Processing call failure: ' . $t->getMessage(), [
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ]);
            });
    }

    public function parseElements(mixed $input, Channel $channel): PromiseInterface
    {
        $restXml = $input;

        assert($restXml instanceof RestXmlElement);

        $sequence = [];

        for ($restXml->rewind(); $restXml->valid(); $restXml->next()) {
            $restXmlElement = $restXml->current();
            $restXmlElementType = $restXmlElement->getName();

            if (!isset($this->parsers[$restXmlElementType])) {
                throw new RestXmlFormatException('Unrecognized Element: ' . $restXmlElementType);
            }

            $parser = $this->parsers[$restXmlElementType];

            if (empty($parser::NESTABLES)) {
                if ($restXmlElement->hasChildren()) {
                    throw new RestXmlFormatException($restXmlElementType . ' cannot have any children!');
                }
            } else {
                foreach ($restXmlElement as $childType => $childData) {
                    if (!in_array($childType, $parser::NESTABLES)) {
                        throw new RestXmlFormatException(($childType ?: '<unnamed>') . ' is not nestable inside ' . $restXmlElementType);
                    }
                }
            }

            assert($restXmlElement instanceof RestXmlElement);

            $element = $parser->parse($restXmlElement, $channel);
            $sequence[] = $element;
        }

        return all($sequence);
    }

    /**
     * Builds a playback array (to be concatenated for file_string://)
     *
     * @param Channel $channel
     * @param list<AbstractElement> $elements
     * @param list<string> $types
     *
     * @return list<string>
     */
    public function buildPlaybackArray(Channel $channel, array $elements, array $types): array
    {
        $media = [];

        foreach ($elements as $element) {
            $elementType = get_class($element);

            if (!in_array($elementType, $types)) {
                continue;
            }

            switch ($elementType) {
                case PlaybackElement::class:
                    assert($element instanceof PlaybackElement);

                    for ($i = 0; $i < $element->loop; $i++) {
                        $media[] = $element->medium;
                    }

                    if ($element->loop === Parser\Play::MAX_LOOPS) {
                        break 2;
                    }
                    break;

                case SpeakElement::class:
                    assert($element instanceof SpeakElement);

                    $element->text = str_replace("'", "\\'", $element->text);

                    if (isset($element->type) && isset($element->method)) {
                        $sayStr = "\${say_string} {$element->language}.wav {$element->language}"
                            . "{$element->type} {$element->method} '{$element->text}'";
                    } else {
                        $sayStr = "say:'{$element->text}'";

                        $channel->ttsEngine = $element->engine;
                        $channel->ttsVoice = $element->voice;
                    }

                    for ($i = 0; $i < $element->loop; $i++) {
                        $media[] = $sayStr;
                    }
                    break;

                case SilenceElement::class:
                    assert($element instanceof SilenceElement);

                    $media[] = 'file_string://silence_stream://' . ($element->duration * 1000);
                    break;
            }
        }

        return $media;
    }

    /**
     * Builds a playback array (to be concatenated for file_string://) from remote RestXML
     *
     * @param AbstractElement $element
     * @param string $url
     * @param list<string> $types
     *
     * @return PromiseInterface
     */
    public function fetchRemotePlaybackArray(AbstractElement $element, string $url, array $types): PromiseInterface
    {
        if (empty($url)) {
            return resolve([]);
        }

        $this->logger->info('Fetching remote sound from RestXML ' . $url);

        return $this->produce($element->channel, $url)
            ->then(function (array $elements) use ($element, $types): PromiseInterface {
                $media = [];

                if (count($elements)) {
                    /** @var list<AbstractElement> $elements */
                    $media = $this->buildPlaybackArray($element->channel, $elements, $types);
                }

                return resolve($media);
            });
    }
}
