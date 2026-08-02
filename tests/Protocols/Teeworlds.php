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
 * Test Class for Teeworlds
 *
 * @package GameQ\Tests\Protocols
 */
class Teeworlds extends Base
{
    /**
     * Holds stub on setup
     *
     * @var \GameQ\Protocols\Teeworlds
     */
    protected \GameQ\Protocols\Teeworlds $stub;

    /**
     * Holds the expected packets for this protocol class
     *
     * @var array<string, string>
     */
    protected array $packets = [
        \GameQ\Protocol::PACKET_ALL => "\xff\xff\xff\xff\xff\xff\xff\xff\xff\xff\x67\x69\x65\x33\x05",
    ];

    /**
     * Setup
     *
     */
    #[\PHPUnit\Framework\Attributes\Before]
    public function customSetUp(): void
    {
        // Create the stub class
        $this->stub = new \GameQ\Protocols\Teeworlds();
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
        $source = self::fixtureContents(sprintf('%s/Providers/Teeworlds/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace(
            "\xff\xff\xff\xff\xff\xff\xff\xff\xff\xffinf35",
            "\xff\xff\xff\xff\xff\xff\xff\xff\xff\xffinf36",
            $source,
        );

        // Should show up as offline
        $testResult = $this->queryTest(
            '127.0.0.1:8303',
            'teeworlds',
            explode(PHP_EOL . '||' . PHP_EOL, $source),
            false,
        );

        self::assertFalse($testResult['gq_online']);
    }

    /**
     * Test for invalid packet type in response
     */
    public function testInvalidPacketTypeDebug(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains(
            "GameQ\Protocols\Teeworlds::processResponse response type 'ffffffffffffffffffff696e663336' is not valid",
        );

        // Read in a css source file
        $source = self::fixtureContents(sprintf('%s/Providers/Teeworlds/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace(
            "\xff\xff\xff\xff\xff\xff\xff\xff\xff\xffinf35",
            "\xff\xff\xff\xff\xff\xff\xff\xff\xff\xffinf36",
            $source,
        );

        // Should show up as offline
        $this->queryTest('127.0.0.1:8303', 'teeworlds', explode(PHP_EOL . '||' . PHP_EOL, $source), true);
    }

    /**
     * Test responses for Teeworlds
     *
     *
     * @param list<string> $responses
     * @param non-empty-array<string, array<string, mixed>> $result
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('loadData')]
    public function testResponses(array $responses, array $result): void
    {
        \GameQ\Tests\MockDNS::mockHosts([
            'ddracepro.net' => '195.154.113.141',
        ]);

        // Pull the first key off the array this is the server ip:port
        $server = self::firstServerKey($result);

        $testResult = $this->queryTest(
            $server,
            'teeworlds',
            $responses,
            false,
            [],
        );

        self::assertEquals($result[$server], $testResult);
    }
}
