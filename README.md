# GameQ

[![CI](https://github.com/SoftCreatRMedia/GameQ/actions/workflows/Tests.yml/badge.svg)](https://github.com/SoftCreatRMedia/GameQ/actions/workflows/Tests.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/softcreatr/gameq.svg)](https://packagist.org/packages/softcreatr/gameq)
[![PHP Version](https://img.shields.io/packagist/dependency-v/softcreatr/gameq/php.svg)](https://packagist.org/packages/softcreatr/gameq)
[![License](https://img.shields.io/badge/license-LGPL--3.0--or--later-blue.svg)](LICENSE.lgpl)

GameQ is a PHP library for querying many kinds of multiplayer game and voice servers. A single `GameQ` instance can query mixed UDP, TCP, TLS, HTTP, and master-list protocols and return a consistent result structure.

This repository is the maintained [SoftCreatR Media fork](https://github.com/SoftCreatRMedia/GameQ) of [Austinb/GameQ](https://github.com/Austinb/GameQ). Version 5 targets PHP 8.1 and newer, retains the GameQ 4.x public and protected API, and adds current protocol support, stricter parsing, bounded batching, and modern quality checks.

## Highlights

- 170 game, voice-server, and generic protocol identifiers.
- Concurrent mixed-protocol queries with configurable batch and response limits.
- Normalized `gq_*` fields plus protocol-native data, players, teams, and join links.
- Broad coverage through established families such as Source and GoldSource, GameSpy, Quake, Unreal, Doom 3, Frostbite, RakNet, and dedicated voice-server protocols.
- Direct UDP, TCP, TLS, and SSL queries alongside protocols that use HTTP APIs, plugins, or public master lists.
- PHPStan at maximum level, PHPUnit coverage for captured protocol responses, and automated compatibility checks against GameQ 4.0.0.

## Installation

Composer is recommended:

```sh
composer require softcreatr/gameq:^5.0
```

GameQ requires PHP 8.1 or newer and the `curl`, `libxml`, `simplexml`, and `xml` extensions. The optional `bz2` extension is only needed to decode compressed Source/A2S split responses. See the [installation guide](https://github.com/SoftCreatRMedia/GameQ/wiki/Installation) for standalone loading and platform details.

## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use GameQ\GameQ;

$gameQ = new GameQ();
$gameQ->addServers([
    [
        'id' => 'source-server',
        'type' => 'css',
        'host' => '192.0.2.10:27015',
    ],
    [
        'id' => 'unreal-server',
        'type' => 'ut2004',
        'host' => '192.0.2.20:7777',
    ],
]);

$gameQ
    ->setOption('timeout', 5)
    ->setOption('max_servers_per_batch', 50);

$results = $gameQ->process();

if ($results['source-server']['gq_online']) {
    printf(
        "%s: %d/%d players\n",
        $results['source-server']['gq_hostname'],
        $results['source-server']['gq_numplayers'],
        $results['source-server']['gq_maxplayers'],
    );
}
```

The port in `host` is always the client/connect port. GameQ calculates the query port where a protocol has a known offset; use the per-server `query_port` option when the server uses a custom query port.

## Documentation

Project documentation is maintained in the separate [GameQ Wiki](https://github.com/SoftCreatRMedia/GameQ/wiki), updated from the useful parts of the [upstream wiki](https://github.com/Austinb/GameQ/wiki) for this fork and version 5.

| Start here | Operate GameQ | Extend GameQ |
|---|---|---|
| [Installation](https://github.com/SoftCreatRMedia/GameQ/wiki/Installation) | [Global options](https://github.com/SoftCreatRMedia/GameQ/wiki/Global-Options) | [Architecture](https://github.com/SoftCreatRMedia/GameQ/wiki/Architecture) |
| [Quick start and examples](https://github.com/SoftCreatRMedia/GameQ/wiki/Quick-Start) | [Results and normalized fields](https://github.com/SoftCreatRMedia/GameQ/wiki/Results) | [Adding a protocol](https://github.com/SoftCreatRMedia/GameQ/wiki/Adding-a-Protocol) |
| [Server definitions and ports](https://github.com/SoftCreatRMedia/GameQ/wiki/Servers) | [Performance and batching](https://github.com/SoftCreatRMedia/GameQ/wiki/Performance) | [Tests and fixtures](https://github.com/SoftCreatRMedia/GameQ/wiki/Testing) |
| [Supported identifiers](https://github.com/SoftCreatRMedia/GameQ/wiki/Supported-Servers) and [protocol families](https://github.com/SoftCreatRMedia/GameQ/wiki/Protocol-Families) | [Troubleshooting](https://github.com/SoftCreatRMedia/GameQ/wiki/Troubleshooting) | [Upgrading from 4.x](https://github.com/SoftCreatRMedia/GameQ/wiki/Upgrading-from-4.x) |

Protocol-specific credentials and HTTP endpoints need additional care. Read [protocol options](https://github.com/SoftCreatRMedia/GameQ/wiki/Protocol-Options) and [security guidance](https://github.com/SoftCreatRMedia/GameQ/wiki/Security) before exposing queries through a public application.

## Support and contributing

- Report reproducible problems through [GitHub Issues](https://github.com/SoftCreatRMedia/GameQ/issues).
- See [CONTRIBUTING.md](CONTRIBUTING.md) before submitting changes.
- Run `composer test` for the complete local quality suite.
- Review [CHANGELOG.md](CHANGELOG.md) before upgrading.

## License

GameQ is licensed under the [GNU Lesser General Public License 3.0 or later](LICENSE.lgpl).
