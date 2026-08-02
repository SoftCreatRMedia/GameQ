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

use GameQ\Buffer;
use ReflectionClass;

/**
 * Test Class for StarMade
 *
 * @package GameQ\Tests\Protocols
 */
class Starmade extends Base
{
    /**
     * Holds stub on setup
     *
     * @var \GameQ\Protocols\Starmade
     */
    protected \GameQ\Protocols\Starmade $stub;

    /**
     * Holds the expected packets for this protocol class
     *
     * @var array<string, string>
     */
    protected array $packets = [
        \GameQ\Protocol::PACKET_STATUS => "\x00\x00\x00\x09\x2a\xff\xff\x01\x6f\x00\x00\x00\x00",
    ];

    /**
     * Setup
     *
     */
    #[\PHPUnit\Framework\Attributes\Before]
    public function customSetUp(): void
    {
        // Create the stub class
        $this->stub = new \GameQ\Protocols\Starmade();
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
     * Test responses for Starmade
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
            'starmade',
            $responses,
        );

        self::assertEqualsDelta($result[$server], $testResult, 0.000000001);
    }

    /**
     * Test byte-array parameter decoding
     */
    public function testByteArrayParameter(): void
    {
        $buffer = new Buffer(
            pack('N', 1) . "\x08" . pack('N', 3) . "\x00\x7F\xFF",
            Buffer::NUMBER_TYPE_BIGENDIAN,
        );
        $method = (new ReflectionClass($this->stub))->getMethod('parseServerParameters');

        self::assertSame([[0, 127, 255]], $method->invoke($this->stub, $buffer));
    }
}
