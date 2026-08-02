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

class Raknet extends Base
{
    /**
     * Holds stub on setup
     *
     * @var \GameQ\Protocols\Raknet
     */
    protected \GameQ\Protocols\Raknet $stub;

    /**
     * Holds the expected packets for this protocol class
     *
     * @var array<string, string>
     */
    protected array $packets = [
        \GameQ\Protocol::PACKET_STATUS => "\x01%s%s\x02\x00\x00\x00\x00\x00\x00\x00",
    ];

    /**
     * Setup
     *
     */
    #[\PHPUnit\Framework\Attributes\Before]
    public function customSetUp(): void
    {
        // Create the stub class
        $this->stub = new \GameQ\Protocols\Raknet();
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
     * Test that building the status packet repeatedly always uses the original template
     */
    public function testStatusPacketCanBeRebuilt(): void
    {
        $server = new \GameQ\Server([
            \GameQ\Server::SERVER_TYPE => 'minecraftbe',
            \GameQ\Server::SERVER_HOST => '127.0.0.1:19132',
        ]);

        $this->stub->beforeSend($server);
        $firstPacket = $this->stub->getPacket(\GameQ\Protocol::PACKET_STATUS);
        $this->stub->beforeSend($server);

        self::assertIsString($firstPacket);
        self::assertSame(33, strlen($firstPacket));
        self::assertSame($firstPacket, $this->stub->getPacket(\GameQ\Protocol::PACKET_STATUS));
    }

    /**
     * Test invalid packet type without debug
     */
    public function testInvalidPacketType(): void
    {
        // Read in a Minecraft BE source file
        $source = self::fixtureContents(sprintf('%s/Providers/Minecraftbe/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace(\GameQ\Protocols\Raknet::ID_UNCONNECTED_PONG, "\x1D", $source);

        // Should show up as offline
        $testResult = $this->queryTest(
            '127.0.0.1:19132',
            'minecraftbe',
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
            "GameQ\Protocols\Raknet::processResponse The header returned \"1d\" "
            . "does not match the expected header of \"1c\"",
        );

        // Read in a Minecraft BE source file
        $source = self::fixtureContents(sprintf('%s/Providers/Minecraftbe/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace(\GameQ\Protocols\Raknet::ID_UNCONNECTED_PONG, "\x1D", $source);

        // Should fail out
        $this->queryTest('127.0.0.1:19132', 'minecraftbe', explode(PHP_EOL . '||' . PHP_EOL, $source), true);
    }

    /**
     * Test invalid magic without debug
     */
    public function testInvalidMagic(): void
    {
        // Read in a Minecraft BE source file
        $source = self::fixtureContents(sprintf('%s/Providers/Minecraftbe/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace(\GameQ\Protocols\Raknet::OFFLINE_MESSAGE_DATA_ID, "\xFF\xFF", $source);

        // Should show up as offline
        $testResult = $this->queryTest(
            '127.0.0.1:19132',
            'minecraftbe',
            explode(PHP_EOL . '||' . PHP_EOL, $source),
            false,
        );

        self::assertFalse($testResult['gq_online']);
    }

    /**
     * Test invalid magic with debug
     */
    public function testInvalidMagicDebug(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains(
            "GameQ\Protocols\Raknet::processResponse The magic value returned \"ffff00bc4d4350453bc2a772c2a761c2\" "
            . "does not match the expected value of \"00ffff00fefefefefdfdfdfd12345678\"",
        );

        // Read in a Minecraft BE source file
        $source = self::fixtureContents(sprintf('%s/Providers/Minecraftbe/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace(\GameQ\Protocols\Raknet::OFFLINE_MESSAGE_DATA_ID, "\xFF\xFF", $source);

        // Should fail out
        $this->queryTest('127.0.0.1:19132', 'minecraftbe', explode(PHP_EOL . '||' . PHP_EOL, $source), true);
    }

    /**
     * Test that the declared server information length is validated
     */
    public function testInvalidServerInformationLength(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains(
            'RakNet response declares 189 server information bytes, but contains 188.',
        );

        $source = self::fixtureContents(sprintf('%s/Providers/Minecraftbe/1_response.txt', __DIR__));
        $packets = explode(PHP_EOL . '||' . PHP_EOL, $source);
        $lengthOffset = 1 + 8 + 8 + strlen(\GameQ\Protocols\Raknet::OFFLINE_MESSAGE_DATA_ID);
        $packets[0] = substr_replace($packets[0], pack('n', 189), $lengthOffset, 2);

        $this->queryTest('127.0.0.1:19132', 'minecraftbe', $packets, true);
    }
}
