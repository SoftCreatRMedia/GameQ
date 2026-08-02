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

/**
 * Test Class for Teamspeak2
 *
 * @package GameQ\Tests\Protocols
 */
class Teamspeak2 extends Base
{
    /**
     * Holds stub on setup
     *
     * @var \GameQ\Protocols\Teamspeak2
     */
    protected \GameQ\Protocols\Teamspeak2 $stub;

    /**
     * Holds the expected packets for this protocol class
     *
     * @var array<string, string>
     */
    protected array $packets = [
        \GameQ\Protocol::PACKET_DETAILS  => "sel %d\x0asi\x0a",
        \GameQ\Protocol::PACKET_CHANNELS => "sel %d\x0acl\x0a",
        \GameQ\Protocol::PACKET_PLAYERS  => "sel %d\x0apl\x0a",
    ];

    /**
     * Setup
     *
     */
    #[\PHPUnit\Framework\Attributes\Before]
    public function customSetUp(): void
    {
        // Create the stub class
        $this->stub = new \GameQ\Protocols\Teamspeak2();
    }

    /**
     * Test the packets to make sure they are correct for source
     */
    public function testPackets(): void
    {
        // Test to make sure packets are defined properly
        self::assertEquals($this->packets, $this->stub->getPacket());
    }

    /**
     * Test for exception being thrown if missing query_port
     */
    public function testMissingQueryPort(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains("GameQ\Protocols\Teamspeak2::beforeSend Missing required setting 'query_port'.");
        $client_port = 8767;
        $query_port = 51234;

        $server = new \GameQ\Server([
            \GameQ\Server::SERVER_HOST    => "127.0.0.1:$client_port",
            \GameQ\Server::SERVER_TYPE    => 'teamspeak2',
            \GameQ\Server::SERVER_OPTIONS => [
                \GameQ\Server::SERVER_OPTIONS_QUERY_PORT => $query_port,
            ],
        ]);

        // Apply the before send, should throw exception
        $this->stub->beforeSend($server);
    }

    /**
     * Test the packets to see if they set
     *
     */
    #[\PHPUnit\Framework\Attributes\Depends('testMissingQueryPort')]
    public function testBeforeSend(): void
    {
        $client_port = 8767;
        $query_port = 51234;

        // Set what the packets should look like
        $packets = [
            \GameQ\Protocol::PACKET_DETAILS  => "sel $client_port\x0asi\x0a",
            \GameQ\Protocol::PACKET_CHANNELS => "sel $client_port\x0acl\x0a",
            \GameQ\Protocol::PACKET_PLAYERS  => "sel $client_port\x0apl\x0a",
        ];

        // Create a mock server
        $server = new \GameQ\Server([
            \GameQ\Server::SERVER_HOST    => "127.0.0.1:$client_port",
            \GameQ\Server::SERVER_TYPE    => 'teamspeak2',
            \GameQ\Server::SERVER_OPTIONS => [
                \GameQ\Server::SERVER_OPTIONS_QUERY_PORT => $query_port,
            ],
        ]);

        $stub = new \GameQ\Protocols\Teamspeak2([
            \GameQ\Server::SERVER_OPTIONS_QUERY_PORT => $query_port,
        ]);

        // Apply the before send
        $stub->beforeSend($server);

        self::assertEquals(
            $packets,
            $stub->getPacket(),
        );
    }

    /**
     * Test for invalid header
     */
    public function testInvalidHeader(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains(
            "GameQ\Protocols\Teamspeak2::processResponse Expected header 'BadH' does not match expected '[TS]'.",
        );

        $client_port = 8767;
        $query_port = 51234;

        // Read in a css source file
        $source = self::fixtureContents(sprintf('%s/Providers/Teamspeak2/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace("[TS]", "BadH", $source);

        // Should throw an exception
        $this->queryTest(
            '127.0.0.1:' . $client_port,
            'teamspeak2',
            explode(PHP_EOL . '||' . PHP_EOL, $source),
            true,
            [
                \GameQ\Server::SERVER_OPTIONS_QUERY_PORT => $query_port,
            ],
        );
    }

    /**
     * Test responses for Teamspeak2
     *
     *
     * @param list<string> $responses
     * @param non-empty-array<string, array<string, mixed>> $result
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('loadData')]
    public function testResponses(array $responses, array $result): void
    {
        // Pull the first key off the array this is the server ip:port
        $server = self::firstServerKey($result);

        $testResult = $this->queryTest(
            $server,
            'teamspeak2',
            $responses,
            false,
            [
                \GameQ\Server::SERVER_OPTIONS_QUERY_PORT => $result[$server]['gq_port_query'],
            ],
        );

        self::assertEquals($result[$server], $testResult);
    }
}
