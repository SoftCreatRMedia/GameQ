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

class Lhmp extends Base
{
    /**
     * Holds stub on setup
     *
     * @var \GameQ\Protocols\Lhmp
     */
    protected \GameQ\Protocols\Lhmp $stub;

    /**
     * Holds the expected packets for this protocol class
     *
     * @var array<string, string>
     */
    protected array $packets = [
        \GameQ\Protocol::PACKET_DETAILS => "LHMPo",
        \GameQ\Protocol::PACKET_PLAYERS => "LHMPp",
    ];

    /**
     * Setup
     *
     */
    #[\PHPUnit\Framework\Attributes\Before]
    public function customSetUp(): void
    {
        // Create the stub class
        $this->stub = new \GameQ\Protocols\Lhmp();
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
        // Read in a lhmp source file
        $source = self::fixtureContents(sprintf('%s/Providers/Lhmp/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace("LHMPo", "LHMPz", $source);

        // Should show up as offline
        $testResult = $this->queryTest('127.0.0.1:27015', 'lhmp', explode(PHP_EOL . '||' . PHP_EOL, $source), false);

        self::assertFalse($testResult['gq_online']);
    }

    /**
     * Test for invalid packet type in response
     */
    public function testInvalidPacketTypeDebug(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains("GameQ\Protocols\Lhmp::processResponse response type 'LHMPz' is not valid");

        // Read in a lhmp source file
        $source = self::fixtureContents(sprintf('%s/Providers/Lhmp/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace("LHMPo", "LHMPz", $source);

        // Should show up as offline
        $this->queryTest('127.0.0.1:27015', 'lhmp', explode(PHP_EOL . '||' . PHP_EOL, $source), true);
    }
}
