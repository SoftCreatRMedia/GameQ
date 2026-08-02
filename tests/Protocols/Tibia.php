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

class Tibia extends Base
{
    /**
     * Holds stub on setup
     *
     * @var \GameQ\Protocols\Tibia
     */
    protected \GameQ\Protocols\Tibia $stub;

    /**
     * Holds the expected packets for this protocol class
     *
     * @var array<string, string>
     */
    protected array $packets = [
        \GameQ\Protocol::PACKET_STATUS => "\x06\x00\xFF\xFF\x69\x6E\x66\x6F",
    ];

    /**
     * Setup
     *
     */
    #[\PHPUnit\Framework\Attributes\Before]
    public function customSetUp(): void
    {
        // Create the stub class
        $this->stub = new \GameQ\Protocols\Tibia();
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
     * Test invalid xml response without debug
     */
    public function testInvalidPacketType(): void
    {
        // Read in a Tibia source file
        $source = self::fixtureContents(sprintf('%s/Providers/Tibia/1_response.txt', __DIR__));

        // Add bogus characters to the response
        $source = 'data' . $source . 'data';

        // Should show up as offline
        $testResult = $this->queryTest('127.0.0.1:7171', 'tibia', explode(PHP_EOL . '||' . PHP_EOL, $source), false);

        self::assertFalse($testResult['gq_online']);
    }

    /**
     * Test for invalid response in response
     */
    public function testInvalidPacketTypeDebug(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains("GameQ\Protocols\Tibia::processResponse Unable to load XML string.");

        // Read in a Tibia source file
        $source = self::fixtureContents(sprintf('%s/Providers/Tibia/1_response.txt', __DIR__));

        // Add bogus characters to the response
        $source = 'data' . $source . 'data';

        // Should show up as offline
        $this->queryTest('127.0.0.1:7171', 'tibia', explode(PHP_EOL . '||' . PHP_EOL, $source), true);
    }

    /**
     * Test responses for Tibia
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
            'tibia',
            $responses,
        );

        self::assertEquals($result[$server], $testResult);
    }
}
