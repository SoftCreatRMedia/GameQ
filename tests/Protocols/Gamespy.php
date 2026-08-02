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

class Gamespy extends Base
{
    /**
     * Holds stub on setup
     *
     * @var \GameQ\Protocols\Gamespy
     */
    protected \GameQ\Protocols\Gamespy $stub;

    /**
     * Holds the expected packets for this protocol class
     *
     * @var array<string, string>
     */
    protected array $packets = [
        \GameQ\Protocol::PACKET_STATUS => "\x5C\x73\x74\x61\x74\x75\x73\x5C",
    ];

    /**
     * Setup
     *
     */
    #[\PHPUnit\Framework\Attributes\Before]
    public function customSetUp(): void
    {
        // Create the stub class
        $this->stub = new \GameQ\Protocols\Gamespy();
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
     * Test invalid packet type without debug
     */
    public function testInvalidPacketType(): void
    {
        // Read in a css source file
        $source = self::fixtureContents(sprintf('%s/Providers/Ut/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace('queryid\\20.1', '', $source);

        // Should show up as offline
        $testResult = $this->queryTest('127.0.0.1:7777', 'ut', explode(PHP_EOL . '||' . PHP_EOL, $source), false);

        self::assertFalse($testResult['gq_online']);
    }

    /**
     * Test for invalid packet type in response
     */
    public function testInvalidPacketTypeDebug(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains(
            "GameQ\Protocols\Gamespy::processResponse An error occurred while parsing the packets for 'queryid'",
        );

        // Read in a css source file
        $source = self::fixtureContents(sprintf('%s/Providers/Ut/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace('queryid\\20.1', '', $source);

        // Should show up as offline
        $this->queryTest('127.0.0.1:7777', 'ut', explode(PHP_EOL . '||' . PHP_EOL, $source), true);
    }
}
