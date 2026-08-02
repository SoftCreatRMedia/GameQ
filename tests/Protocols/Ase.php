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

class Ase extends Base
{
    /**
     * Holds stub on setup
     *
     * @var \GameQ\Protocols\Ase
     */
    protected \GameQ\Protocols\Ase $stub;

    /**
     * Holds the expected packets for this protocol class
     *
     * @var array<string, string>
     */
    protected array $packets = [
        \GameQ\Protocol::PACKET_ALL => "s",
    ];

    /**
     * Setup
     *
     */
    #[\PHPUnit\Framework\Attributes\Before]
    public function customSetUp(): void
    {
        // Create the stub class
        $this->stub = new \GameQ\Protocols\Ase();
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
        $source = self::fixtureContents(sprintf('%s/Providers/Mta/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace("EYE1", "Something else", $source);

        // Should show up as offline
        $testResult = $this->queryTest('104.156.48.17:22003', 'mta', explode(PHP_EOL . '||' . PHP_EOL, $source), false);

        self::assertFalse($testResult['gq_online']);
    }

    /**
     * Test for invalid packet type in response
     */
    public function testInvalidPacketTypeDebug(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains(
            'GameQ\Protocols\Ase::processResponse The response header "Some" does not match expected "EYE1"',
        );

        // Read in a css source file
        $source = self::fixtureContents(sprintf('%s/Providers/Mta/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace("EYE1", "Something else", $source);

        // Should fail out
        $this->queryTest('104.156.48.17:22003', 'mta', explode(PHP_EOL . '||' . PHP_EOL, $source), true);
    }

    /**
     * Test empty server response without debug
     */
    public function testEmptyServerResponse(): void
    {
        // Should show up as offline
        $testResult = $this->queryTest('46.174.48.50:22051', 'mta', [], false);

        self::assertFalse($testResult['gq_online']);
    }

    /**
     * Test empty server response
     */
    public function testEmptyServerResponseDebug(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains("GameQ\Protocols\Ase::processResponse The response from the server was empty.");

        // Should fail out
        $this->queryTest('46.174.48.50:22051', 'mta', [], true);
    }
}
