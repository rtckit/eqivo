<a href="#open-source-telecommunications-api-platform">
  <img loading="lazy" src="https://raw.github.com/rtckit/media/master/eqivo/readme-splash.png" alt="eqivo" class="width-full">
</a>

# Open Source Telecommunications API Platform

[![CI Status](https://github.com/rtckit/eqivo/workflows/CI/badge.svg)](https://github.com/rtckit/eqivo/actions/workflows/ci.yaml)
[![Publish Status](https://github.com/rtckit/eqivo/workflows/Publish/badge.svg)](https://github.com/rtckit/eqivo/actions/workflows/publish.yaml)
[![Psalm Type Coverage](https://shepherd.dev/github/rtckit/eqivo/coverage.svg)](https://shepherd.dev/github/rtckit/eqivo)
[![Community Chat](https://img.shields.io/badge/zulip-join_chat-brightgreen.svg)](https://rtckit.zulipchat.com/#narrow/stream/414323-eqivo)
<br />
[![Latest Stable Version](https://poser.pugx.org/rtckit/eqivo/v/stable.png)](https://packagist.org/packages/rtckit/eqivo)
[![Docker Pulls](https://img.shields.io/docker/pulls/rtckit/eqivo.svg)](https://hub.docker.com/r/rtckit/eqivo)
[![Downloads on GitHub](https://img.shields.io/github/downloads/rtckit/eqivo/total?color=blue&label=Downloads%20on%20GitHub)](https://github.com/rtckit/eqivo/releases)
[![Installs on Packagist](https://img.shields.io/packagist/dt/rtckit/eqivo?color=blue&label=Installs%20on%20Packagist)](https://packagist.org/packages/rtckit/eqivo)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

A reimplementation of the open source [Plivo framework](https://github.com/plivo/plivoframework) on top of [ReactPHP](https://reactphp.org) and [FreeSWITCH](https://github.com/signalwire/freeswitch). If you are not familiar with the legacy platform, please inspect its [repository](https://github.com/plivo/plivoframework) as well as the archived web resources [here](https://web.archive.org/web/20171127130133/http://docs.plivo.org/), [here](https://web.archive.org/web/20171207074507/http://docs.plivo.org/get-started/) and [here](https://web.archive.org/web/20190108064818/https://www.plivo.com/open-source/).

As of [v0.6](https://github.com/rtckit/eqivo/releases/tag/v0.6.0), **Eqivo** builds on top of the _FiCore_ [FreeSWITCH integration library](https://github.com/rtckit/ficore).

For integrating **Eqivo** in your projects, please refer to **[https://eqivo.org](https://eqivo.org)** as well as to **[rtckit/eqivo-sandbox](https://github.com/rtckit/eqivo-sandbox)**. If you want to contribute or to extend this project, keep reading.

## Requirements

**Eqivo** is compatible with PHP 8.1+ and has several extension dependencies, typically bundled with PHP's core. Please refer to [composer.json](composer.json) for details.

### Static Analysis

In order to ensure high code quality, **Eqivo** uses [PHPStan](https://github.com/phpstan/phpstan):

```sh
composer phpstan
```

and [Psalm](https://github.com/vimeo/psalm):

```sh
composer psalm
```

### Tests

Unit tests are presently lacking. The project itself has been scaffolded against an acceptance test suite hosted in [its own repository](https://github.com/rtckit/eqivo-acceptance-test-suite).

## License

MIT, see [LICENSE file](LICENSE).

### Acknowledgments

* [Plivo framework](https://github.com/plivo/plivoframework) - Original framework; Eqivo and its authors are not affiliated with the legacy open source project nor with with the company behind it
* [ReactPHP](https://reactphp.org) - Provides the asynchronous I/O fabric on top of which Eqivo interacts with FreeSWITCH and the consuming applications
* [FreeSWITCH](https://github.com/signalwire/freeswitch) - Handles the real time communications aspects, particularly signalling and media processing
* [FiCore](https://github.com/rtckit/ficore) - FreeSWITCH Integration Core responsible for the bulk of dialplan/call flow logic exposed by Eqivo
* [Slate](https://github.com/slatedocs/slate) is responsible for rendering the [project's website](https://eqivo.org)
* [widdershins](https://github.com/Mermade/widdershins) translates the OpenApi spec to Markdown
* [Zulip](https://zulip.com/) is then open-source modern team chat platform behind our [community chat](https://rtckit.zulipchat.com/#narrow/stream/414323-eqivo)

### Contributing

Bug reports (and small patches) can be submitted via the [issue tracker](https://github.com/rtckit/eqivo/issues). Forking the repository and submitting a Pull Request is preferred for substantial patches. For more details, please head to [CONTRIBUTING.md](CONTRIBUTING.md).
