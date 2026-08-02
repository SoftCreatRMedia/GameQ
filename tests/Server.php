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

/**
 * Server testing class
 *
 * @package GameQ\Tests
 */
class Server extends TestBase
{
    /**
     * Test for missing server type
     */
    public function testMissingServerType(): void
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessageContains("Missing server info key 'type'!");

        // Create a mock server should throw exception
        new \GameQ\Server([]);
    }

    /**
     * Test for missing host information
     */
    public function testMissingHost(): void
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessageContains("Missing server info key 'host'!");

        // Create a mock server Create a mock server should throw exception
        new \GameQ\Server([
            \GameQ\Server::SERVER_TYPE => 'source',
        ]);
    }

    /**
     * Test setting server options
     */
    public function testSetServerOptions(): void
    {
        $options = [
            'option1' => 'val1',
            'option2' => 'val2',
        ];

        // Create a server with some options
        $server = new \GameQ\Server([
            \GameQ\Server::SERVER_HOST    => '127.0.0.1:27015',
            \GameQ\Server::SERVER_TYPE    => 'source',
            \GameQ\Server::SERVER_OPTIONS => $options,
        ]);

        self::assertEquals($options, $server->getOptions());

        // Check the getOption
        self::assertEquals($options['option1'], $server->getOption('option1'));

        // Check the get null for missing option
        self::assertNull($server->getOption('doesnotexist'));

        // Check the setOption
        $server->setOption('option3', 'valnew');

        self::assertEquals('valnew', $server->getOption('option3'));
    }

    /**
     * Test that the server id is behaving properly
     */
    public function testServerId(): void
    {
        $id = '127.0.0.1:27015';

        // Create a server with id
        $server = new \GameQ\Server([
            \GameQ\Server::SERVER_HOST => $id,
            \GameQ\Server::SERVER_TYPE => 'source',
        ]);

        self::assertEquals($id, $server->id());

        $id = 'my_server_#1';

        // Create a server with id
        $server = new \GameQ\Server([
            \GameQ\Server::SERVER_HOST => '127.0.0.1:27015',
            \GameQ\Server::SERVER_TYPE => 'source',
            \GameQ\Server::SERVER_ID   => $id,
        ]);

        self::assertEquals($id, $server->id());
    }

    /**
     * Test ipv4 missing port
     */
    public function testIpv4NoPort(): void
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessageContains("The host address '127.0.0.1' is missing the port. All servers must have a port defined!");

        // Create a mock server
        new \GameQ\Server([
            \GameQ\Server::SERVER_HOST => '127.0.0.1',
            \GameQ\Server::SERVER_TYPE => 'source',
        ]);
    }

    /**
     * Test IPv4 unresolvable hostname
     */
    public function testIpv4UnresovlableHostname(): void
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessageContains("Unable to resolve the host 'some.unresolable.domain' to an IP address.");

        // Create a mock server
        new \GameQ\Server([
            \GameQ\Server::SERVER_HOST => 'some.unresolable.domain:27015',
            \GameQ\Server::SERVER_TYPE => 'source',
        ]);
    }

    /**
     * Test IPv6 host
     */
    public function testIpv6(): void
    {
        // Create a mock server
        $stub = new \GameQ\Server([
            \GameQ\Server::SERVER_HOST => '[::1]:27015',
            \GameQ\Server::SERVER_TYPE => 'source',
        ]);

        self::assertEquals('[::1]:27015', $stub->id());
    }

    /**
     * Test ipv6 missing port
     */
    public function testIpv6NoPort(): void
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessageContains("The host address '[::1]' is missing the port.  All servers must have a port defined!");

        // Create a mock server
        new \GameQ\Server([
            \GameQ\Server::SERVER_HOST => '[::1]',
            \GameQ\Server::SERVER_TYPE => 'source',
        ]);
    }

    /**
     * Test invalid ipv6
     */
    public function testIpv6Invalid(): void
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessageContains("The IPv6 address '[:0:1]' is invalid.");

        // Create a mock server
        new \GameQ\Server([
            \GameQ\Server::SERVER_HOST => '[:0:1]:27015',
            \GameQ\Server::SERVER_TYPE => 'source',
        ]);
    }

    /**
     * Test invalid protocol
     */
    public function testInvalidProtocol(): void
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessageContains("Unable to locate Protocols class for 'doesnotexist'!");

        // Create a mock server
        new \GameQ\Server([
            \GameQ\Server::SERVER_HOST => '127.0.0.1:27015',
            \GameQ\Server::SERVER_TYPE => 'doesnotexist',
        ]);
    }

    /**
     * Test for specific query port defined in server creation
     */
    public function testSpecifiedQueryPort(): void
    {
        $query_port = 27016;

        // Create a mock server
        $server = new \GameQ\Server([
            \GameQ\Server::SERVER_HOST    => '127.0.0.1:27015',
            \GameQ\Server::SERVER_TYPE    => 'source',
            \GameQ\Server::SERVER_OPTIONS => [
                \GameQ\Server::SERVER_OPTIONS_QUERY_PORT => $query_port,
            ],
        ]);

        self::assertEquals($query_port, $server->port_query);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function invalidPorts(): iterable
    {
        yield 'non-numeric client port' => ['127.0.0.1:not-a-port', [], 'client'];
        yield 'zero client port' => ['127.0.0.1:0', [], 'client'];
        yield 'oversized client port' => ['127.0.0.1:65536', [], 'client'];
        yield 'non-numeric query port' => [
            '127.0.0.1:27015',
            [\GameQ\Server::SERVER_OPTIONS_QUERY_PORT => 'not-a-port'],
            'query',
        ];
        yield 'oversized derived query port' => ['127.0.0.1:65000', [], 'query'];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidPorts')]
    public function testInvalidPortsAreRejected(string $host, array $options, string $type): void
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessageContains("The $type port must be an integer between 1 and 65535.");

        new \GameQ\Server([
            \GameQ\Server::SERVER_HOST => $host,
            \GameQ\Server::SERVER_TYPE => $type === 'query' && $options === [] ? 'atlas' : 'source',
            \GameQ\Server::SERVER_OPTIONS => $options,
        ]);
    }
}
