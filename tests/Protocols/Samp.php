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

namespace GameQ\Tests\Protocols;

use GameQ\Exception\ProtocolException;
use GameQ\Exception\ServerException;
use GameQ\Protocol;
use GameQ\Server;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionException;

class Samp extends Base
{
    /**
     * Holds stub on setup
     *
     * @var \GameQ\Protocols\Samp
     */
    protected \GameQ\Protocols\Samp $stub;

    /**
     * Holds the expected packets for this protocol class
     *
     * @var array<string, string>
     */
    protected array $packets = [
        Protocol::PACKET_STATUS  => "SAMP%si",
        Protocol::PACKET_PLAYERS => "SAMP%sd",
        Protocol::PACKET_RULES   => "SAMP%sr",
    ];

    /**
     * Setup
     */
    #[Before]
    public function customSetUp(): void
    {
        // Create the stub class
        $this->stub = new \GameQ\Protocols\Samp();
    }

    /**
     * Test the packets to make sure they are correct for source
     *
     * @throws ProtocolException
     */
    public function testPackets(): void
    {
        // Test to make sure packets are defined properly
        self::assertEquals($this->packets, $this->stub->getPacket());
    }

    /**
     * @throws ProtocolException
     * @throws ServerException
     */
    public function testServerCodeUsesDocumentedLittleEndianPortBytes(): void
    {
        $server = new Server([
            Server::SERVER_HOST => '127.0.0.1:7777',
            Server::SERVER_TYPE => 'samp',
        ]);
        $server->protocolInstance()->beforeSend($server);
        $packet = $server->protocolInstance()->getPacket(Protocol::PACKET_STATUS);

        self::assertSame("SAMP\x7F\x00\x00\x01\x61\x1Ei", $packet);
    }

    /**
     * @throws ServerException
     * @throws ProtocolException
     */
    public function testServerCodeUsesConfiguredQueryPort(): void
    {
        $server = new Server([
            Server::SERVER_HOST => '127.0.0.1:7777',
            Server::SERVER_TYPE => 'samp',
            Server::SERVER_OPTIONS => ['query_port' => 7778],
        ]);
        $server->protocolInstance()->beforeSend($server);
        $packet = $server->protocolInstance()->getPacket(Protocol::PACKET_STATUS);

        self::assertSame("SAMP\x7F\x00\x00\x01\x62\x1Ei", $packet);
    }

    /**
     * Test the packer header check application
     *
     * @throws ServerException
     * @throws ReflectionException
     */
    public function testPacketHeader(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains("GameQ\Protocols\Samp::processResponse header response 'SAMu' is not valid");

        // Read in a samp source file
        $source = self::fixtureContents(sprintf('%s/Providers/Samp/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace("SAMP", "SAMu", $source);

        // Should fail out
        $this->queryTest('127.0.0.1:27015', 'samp', explode(PHP_EOL . '||' . PHP_EOL, $source), true);
    }

    /**
     * Test for mis matched server code in response
     *
     * @throws ServerException
     * @throws ReflectionException
     */
    public function testServerCode(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains("GameQ\Protocols\Samp::processResponse code check failed.");
        // Read in a samp source file
        $source = self::fixtureContents(sprintf('%s/Providers/Samp/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace("SAMP\x5d\x77\x1a\xc9\x61\x1ei", "SAMP\x5d\x77\x1a\xc9\x61\x1fi", $source);

        // Should fail out
        $this->queryTest('93.119.26.201:7777', 'samp', explode(PHP_EOL . '||' . PHP_EOL, $source), true);
    }

    /**
     * Test for invalid packet type in response
     *
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testInvalidPacketTypeDebug(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains("GameQ\Protocols\Samp::processResponse response type 'X' is not valid");

        // Read in a samp source file
        $source = self::fixtureContents(sprintf('%s/Providers/Samp/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace("SAMP\x5d\x77\x1a\xc9\x61\x1ei", "SAMP\x5d\x77\x1a\xc9\x61\x1eX", $source);

        // Should fail out
        $this->queryTest('93.119.26.201:7777', 'samp', explode(PHP_EOL . '||' . PHP_EOL, $source), true);
    }

    /**
     * Test responses for San Andreas Multiplayer
     *
     * @param list<string> $responses
     * @param non-empty-array<string, array<string, mixed>> $result
     *
     * @throws ServerException
     * @throws ReflectionException
     */
    #[DataProvider('loadData')]
    public function testResponses(array $responses, array $result): void
    {
        // Pull the first key off the array this is the server ip:port
        $server = self::firstServerKey($result);

        $testResult = $this->queryTest(
            $server,
            'samp',
            $responses,
        );

        self::assertEquals($result[$server], $testResult);
    }
}
