<?php

namespace GameQ\Tests;

use InvalidArgumentException;

/**
 * MockDNS class using monkey patching. Inspired by symfony/phpunit-bridge
 *
 * @see https://github.com/symfony/phpunit-bridge/blob/5.3/DnsMock.php
 */
class MockDNS
{
    /** @var array<string, string> */
    private static array $hosts = [];

    /**
     * @param array<string, string> $hosts
     */
    public static function mockHosts(array $hosts): void
    {
        self::$hosts = $hosts;
    }

    public static function gethostbyname(string $hostname): string
    {
        // Redirect to original function if no overwrites has been defined
        if (self::$hosts === []) {
            return \gethostbyname($hostname);
        }

        // Return an override when available, or retain the host name on lookup failure.
        return self::$hosts[$hostname] ?? $hostname;
    }

    /**
     * @param class-string $class
     */
    public static function register(string $class): void
    {
        // Store own namespace
        $self = static::class;

        $namespaceSeparator = strrpos($class, '\\');

        if ($namespaceSeparator === false) {
            throw new InvalidArgumentException("Class '$class' does not have a namespace.");
        }

        $ns = substr($class, 0, $namespaceSeparator);

        if (\function_exists($ns . '\gethostbyname')) {
            return;
        }


        $code = <<<EOPHP
namespace $ns;
function gethostbyname(\$hostname)
{
    return \\$self::gethostbyname(\$hostname);
}
EOPHP;

        // Eval the script below, will define the function in the namespace effectively overwriting it
        // https://www.php.net/manual/de/language.namespaces.fallback.php#116275
        eval($code);
    }
}
