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

class Gamespy3 extends Base
{
    /**
     * Holds stub on setup
     *
     * @var \GameQ\Protocols\Gamespy3
     */
    protected \GameQ\Protocols\Gamespy3 $stub;

    /**
     * Holds the expected packets for this protocol class
     *
     * @var array<string, string>
     */
    protected array $packets = [
        \GameQ\Protocol::PACKET_CHALLENGE => "\xFE\xFD\x09\x10\x20\x30\x40",
        \GameQ\Protocol::PACKET_ALL       => "\xFE\xFD\x00\x10\x20\x30\x40%s\xFF\xFF\xFF\x01",
    ];

    /**
     * Setup
     *
     */
    #[\PHPUnit\Framework\Attributes\Before]
    public function customSetUp(): void
    {
        // Create the stub class
        $this->stub = new \GameQ\Protocols\Gamespy3();
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
     * Test the challenge application
     */
    public function testChallengeapply(): void
    {
        $packets = $this->packets;

        //09102030403000

        // Set what the packets should look like
        $packets[\GameQ\Protocol::PACKET_ALL] = "\xfe\xfd\x00\x10\x20\x30\x40\xd7\x13\xb1\x5f\xff\xff\xff\x01";

        // Create a fake buffer
        $challenge_buffer = new \GameQ\Buffer("\x09\x10\x20\x30\x40\x2d\x36\x38\x36\x35\x37\x35\x32\x36\x35\x00");

        // Apply the challenge
        $this->stub->challengeParseAndApply($challenge_buffer);

        self::assertEquals($packets, $this->stub->getPacket());
    }

    /**
     * A partial key at the end of one packet is repeated in full at the start of the next packet.
     */
    public function testPartialKeyAtPacketBoundaryIsRemoved(): void
    {
        $protocol = new class extends \GameQ\Protocols\Gamespy3 {
            /**
             * @param list<string> $packets
             * @return list<string>
             */
            public function cleanPacketList(array $packets): array
            {
                return $this->cleanPackets($packets);
            }
        };

        $packets = [
            "hostname\x00Example server\x00bf2_sponsorlo\x00",
            "bf2_sponsorlogo_url\x00https://example.com/logo.png\x00gametype\x00gpm_cq\x00",
        ];

        self::assertSame([
            "hostname\x00Example server\x00",
            $packets[1],
        ], $protocol->cleanPacketList($packets));
    }

    /**
     * A partial player value can precede the player group header in the next packet.
     */
    public function testPartialPlayerValueAtPacketBoundaryIsRemoved(): void
    {
        $protocol = new class extends \GameQ\Protocols\Gamespy3 {
            /**
             * @param list<string> $packets
             * @return list<string>
             */
            public function cleanPacketList(array $packets): array
            {
                return $this->cleanPackets($packets);
            }
        };

        $packets = [
            "hostname\x00Example server\x00 hekut\x00",
            "player_\x00\x18 hekutooo\x00Bl4ck^Sun\x00",
        ];

        self::assertSame([
            "hostname\x00Example server\x00",
            $packets[1],
        ], $protocol->cleanPacketList($packets));
    }
}
