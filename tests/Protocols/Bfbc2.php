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

class Bfbc2 extends Base
{
    /**
     * Holds stub on setup
     *
     * @var \GameQ\Protocols\Bfbc2
     */
    protected \GameQ\Protocols\Bfbc2 $stub;

    /**
     * Holds the expected packets for this protocol class
     *
     * @var array<string, string>
     */
    protected array $packets = [
        \GameQ\Protocol::PACKET_VERSION => "\x00\x00\x00\x00\x18\x00\x00\x00\x01\x00\x00\x00"
            . "\x07\x00\x00\x00version\x00",
        \GameQ\Protocol::PACKET_STATUS  => "\x00\x00\x00\x00\x1b\x00\x00\x00\x01\x00\x00\x00"
            . "\x0a\x00\x00\x00serverInfo\x00",
        \GameQ\Protocol::PACKET_PLAYERS => "\x00\x00\x00\x00\x24\x00\x00\x00\x02\x00\x00\x00"
            . "\x0b\x00\x00\x00listPlayers\x00\x03\x00\x00\x00\x61ll\x00",
    ];

    /**
     * Setup
     *
     */
    #[\PHPUnit\Framework\Attributes\Before]
    public function customSetUp(): void
    {
        // Create the stub class
        $this->stub = new \GameQ\Protocols\Bfbc2();
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
     * Test for invalid packet length
     */
    public function testInvalidPacketLengthDebug(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains(
            "GameQ\Protocols\Bfbc2::processResponse packet length does not match expected length!",
        );

        // Read in a css source file
        $source = self::fixtureContents(sprintf('%s/Providers/Bfbc2/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace("\x00\x00\x00\x40\x28", "\x00\x00\x00\x40\x29", $source);

        // Should fail out
        $this->queryTest('127.0.0.1:27015', 'bfbc2', explode(PHP_EOL . '||' . PHP_EOL, $source), true);
    }

    /**
     * Test responses for Battlefield Bad Company 2
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
            'bfbc2',
            $responses,
        );

        self::assertEquals($result[$server], $testResult);
    }

    /**
     * Test that responses do not have to arrive in request order
     */
    public function testResponsesAreIdentifiedWithoutRelyingOnOrder(): void
    {
        [$responses, $result] = self::loadData()[0];
        $server = self::firstServerKey($result);

        $testResult = $this->queryTest($server, 'bfbc2', array_reverse($responses));

        self::assertEquals($result[$server], $testResult);
    }
}
