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

class Ffow extends Base
{
    /**
     * Holds stub on setup
     *
     * @var \GameQ\Protocols\Ffow
     */
    protected \GameQ\Protocols\Ffow $stub;

    /**
     * Holds the expected packets for this protocol class
     *
     * @var array<string, string>
     */
    protected array $packets = [
        \GameQ\Protocol::PACKET_CHALLENGE => "\xFF\xFF\xFF\xFF\x57",
        \GameQ\Protocol::PACKET_RULES     => "\xFF\xFF\xFF\xFF\x56%s",
        \GameQ\Protocol::PACKET_PLAYERS   => "\xFF\xFF\xFF\xFF\x55%s",
        \GameQ\Protocol::PACKET_INFO      => "\xFF\xFF\xFF\xFF\x46\x4C\x53\x51",
    ];

    /**
     * Setup
     *
     */
    #[\PHPUnit\Framework\Attributes\Before]
    public function customSetUp(): void
    {
        // Create the stub class
        $this->stub = new \GameQ\Protocols\Ffow();
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

        // Set what the packets should look like
        $packets[\GameQ\Protocol::PACKET_PLAYERS] = "\xFF\xFF\xFF\xFF\x55test";
        $packets[\GameQ\Protocol::PACKET_RULES] = "\xFF\xFF\xFF\xFF\x56test";

        // Create a fake buffer
        $challenge_buffer = new \GameQ\Buffer("\xFF\xFF\xFF\xFF\xFFtest");

        // Apply the challenge
        $this->stub->challengeParseAndApply($challenge_buffer);

        self::assertEquals($packets, $this->stub->getPacket());
    }

    /**
     * Test invalid packet type without debug
     */
    public function testInvalidPacketType(): void
    {
        // Read in a ffow source file
        $source = self::fixtureContents(sprintf('%s/Providers/Ffow/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace("\xFF\xFF\xFF\xFF\x49\x02", "\xFF\xFF\xFF\xFF\x48\x02", $source);

        // Should show up as offline
        $testResult = $this->queryTest('127.0.0.1:5476', 'ffow', explode(PHP_EOL . '||' . PHP_EOL, $source), false);

        self::assertFalse($testResult['gq_online']);
    }

    /**
     * Test for invalid packet type in response
     */
    public function testInvalidPacketTypeDebug(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains(
            "GameQ\Protocols\Ffow::processResponse response type 'ffffffff48' is not valid",
        );

        // Read in a ffow source file
        $source = self::fixtureContents(sprintf('%s/Providers/Ffow/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace("\xFF\xFF\xFF\xFF\x49\x02", "\xFF\xFF\xFF\xFF\x48\x02", $source);

        // Should show up as offline
        $this->queryTest('127.0.0.1:5476', 'ffow', explode(PHP_EOL . '||' . PHP_EOL, $source), true);
    }

    /**
     * Test responses for Ffow
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
            'ffow',
            $responses,
        );

        self::assertEquals($result[$server], $testResult);
    }

    /**
     * Test a populated player-list response using the documented wire format
     */
    public function testSplitPlayers(): void
    {
        $response = pack('N', 0xFFFFFFFF)
            . "\x44\x01\x07Alice\x00"
            . pack('N', 123)
            . pack('G', 12.5)
            . pack('nN', 42, 123456)
            . "\x01";
        $splitAt = intdiv(strlen($response) + 1, 2);
        $chunks = [
            substr($response, 0, $splitAt),
            substr($response, $splitAt),
        ];
        $packets = [
            pack('NNCCn', 0xFFFFFFFE, 123, 0, 2, 1248) . $chunks[0],
            pack('NNCCn', 0xFFFFFFFE, 123, 1, 2, 1248) . $chunks[1],
        ];

        $this->stub->packetResponse(array_reverse($packets));

        self::assertSame(
            [
                'players' => [
                    [
                        'id' => 7,
                        'name' => 'Alice',
                        'score' => 123,
                        'time' => 12.5,
                        'ping' => 42,
                        'profile_id' => 123456,
                        'team' => 1,
                    ],
                ],
            ],
            $this->stub->processResponse(),
        );
    }
}
