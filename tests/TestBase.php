<?php

/**
 * This file is part of GameQ.
 *
 * GameQ is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * GameQ is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace GameQ\Tests;

use GameQ\Exception\ServerException;
use LogicException;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use UnexpectedValueException;

abstract class TestBase extends \PHPUnit\Framework\TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Register MockDNS in the namespaces that resolve hosts during tests.
        MockDNS::register(\GameQ\Server::class);
        MockDNS::register(self::class);
    }

    public static function assertEqualsDelta(
        mixed $expected,
        mixed $actual,
        float $delta,
        string $message = '',
    ): void {
        self::assertEqualsWithDelta($expected, $actual, $delta, $message);
    }

    /**
     * Expect an exception message to contain the given text on every supported PHPUnit version.
     */
    protected function expectExceptionMessageContains(string $message): void
    {
        $this->expectExceptionMessageMatches('~' . preg_quote($message, '~') . '~');
    }

    protected static function fixtureContents(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read test fixture '$path'.");
        }

        return $contents;
    }

    /**
     * @param non-empty-array<string, mixed> $result
     */
    protected static function firstServerKey(array $result): string
    {
        return array_key_first($result);
    }

    /**
     * Generic query test function to simulate testing of protocol classes
     *
     * @param list<string> $responses
     * @param array<string, mixed> $serverOptions
     * @return array<string, mixed>
     * @throws LogicException
     * @throws ReflectionException
     * @throws ServerException
     */
    protected function queryTest(
        string $host,
        string $protocol,
        array $responses,
        bool $debug = false,
        array $serverOptions = [],
    ): array {
        // Create a mock server
        $server = new \GameQ\Server([
            \GameQ\Server::SERVER_HOST    => $host,
            \GameQ\Server::SERVER_TYPE    => $protocol,
            \GameQ\Server::SERVER_OPTIONS => $serverOptions,
        ]);

        // Invoke beforeSend function
        $server->protocolInstance()->beforeSend($server);

        // Set the packet response as if we have really queried it
        $server->protocolInstance()->packetResponse($responses);

        // Create a mock GameQ
        $gq_mock = new \GameQ\GameQ();
        $gq_mock->setOption('debug', $debug);
        $gq_mock->removeFilter('normalize');

        // Reflect on GameQ class so we can parse
        $gameq = new ReflectionClass($gq_mock);

        // Get the parse method so we can call it
        $method = $gameq->getMethod('doParseResponse');

        $testResult = $method->invoke($gq_mock, $server);

        if (!is_array($testResult)) {
            throw new UnexpectedValueException('The test query did not return an array.');
        }

        $parsedResult = [];

        foreach ($testResult as $key => $value) {
            if (!is_string($key)) {
                throw new UnexpectedValueException('The test query returned a non-string result key.');
            }

            $parsedResult[$key] = $value;
        }

        unset($server, $gq_mock, $gameq, $method);

        return $parsedResult;
    }
}
