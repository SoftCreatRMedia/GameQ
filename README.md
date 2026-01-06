# GameQ Fork by Krymo Software

[![CI](https://github.com/KrymoSoftware/GameQ/actions/workflows/Tests.yml/badge.svg)](https://github.com/KrymoSoftware/GameQ/actions/workflows/Tests.yml)
[![License](https://img.shields.io/badge/license-LGPL-blue.svg?style=flat)](https://packagist.org/packages/krymosoftware/gameq)

GameQ is a PHP library that allows you to query multiple types of multiplayer game & voice servers at the same time.

This repository is a maintained fork of [Austinb/GameQ](https://github.com/Austinb/GameQ) by [Krymo Software](https://krymo.software).
While we don't plan to add new games for now, we'll ensure compatibility with the latest PHP versions and fix issues as they arise.

## Requirements

* PHP 8.1+ - [Tested](https://github.com/KrymoSoftware/GameQ/actions/workflows/Tests.yml) in PHP 8.1, 8.2 and 8.3
* [Bzip2](http://www.php.net/manual/en/book.bzip2.php) - Used for A2S compressed responses

## Installation

#### [Composer](https://getcomposer.org/)

This method assumes you already have composer [installed](https://getcomposer.org/doc/00-intro.md) and working properly. Add `krymosoftware/gameq` as a requirement to composer.json by using `composer require krymosoftware/gameq:^4.0.0` or by manually adding the following to the *composer.json* file in the **require** section:

```json
"krymosoftware/gameq": "^4.0.0"
```

Update your packages with `composer update` or install with `composer install`.

#### Standalone Library

Download the [latest version](https://github.com/KrymoSoftware/GameQ/releases) of the library and unpack it into your project. Add the following to your bootstrap file:

```php
require_once('/path/to/src/GameQ/Autoloader.php');
```

The `Autoloader.php` file provides the same autoloading functionality as the Composer installation.

## Useage

```php
$GameQ = new \GameQ\GameQ();
$GameQ->addServer([
    'type' => 'css',
    'host' => '127.0.0.1:27015',
]);
$results = $GameQ->process();
```

Need more? See the [Examples](https://github.com/Austinb/GameQ/wiki/Examples-v3) as well as the [Documentation](https://austinb.github.io/GameQ/api/).

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

See [LICENSE](LICENSE.lgpl) for more information

## Third Party Provider

* [dev.tkirch.wsc.gameq](https://github.com/KrymoSoftware/dev.tkirch.wsc.gameq) - Provides this library as a plugin to WoltLab Suite™ Core.
