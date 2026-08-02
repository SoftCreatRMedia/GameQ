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
use GameQ\Server;

class Source extends Base
{
    /**
     * Holds stub on setup
     *
     * @var \GameQ\Protocols\Source
     */
    protected \GameQ\Protocols\Source $stub;

    /**
     * Holds the expected packets for this protocol class
     *
     * @var array<string, string>
     */
    protected array $packets = [
        \GameQ\Protocol::PACKET_DETAILS => "\xFF\xFF\xFF\xFFTSource Engine Query\x00",
        \GameQ\Protocol::PACKET_PLAYERS => "\xFF\xFF\xFF\xFF\x55\xFF\xFF\xFF\xFF",
        \GameQ\Protocol::PACKET_RULES => "\xFF\xFF\xFF\xFF\x56\xFF\xFF\xFF\xFF",
    ];

    /**
     * Setup
     *
     */
    #[\PHPUnit\Framework\Attributes\Before]
    public function customSetUp(): void
    {
        // Create the stub class
        $this->stub = new \GameQ\Protocols\Source();
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
        $packets[\GameQ\Protocol::PACKET_DETAILS] = "\xFF\xFF\xFF\xFFTSource Engine Query\x00test";
        $packets[\GameQ\Protocol::PACKET_PLAYERS] = "\xFF\xFF\xFF\xFF\x55test";
        $packets[\GameQ\Protocol::PACKET_RULES] = "\xFF\xFF\xFF\xFF\x56test";

        // Create a fake buffer
        $challenge_buffer = new \GameQ\Buffer("\xFF\xFF\xFF\xFF\x41test");

        // Apply the challenge
        $this->stub->challengeParseAndApply($challenge_buffer);

        self::assertEquals($packets, $this->stub->getPacket());
    }

    /**
     * Test that the challenge application is skipped if packet is not a challenge
     */
    public function testSkipChallengeApply(): void
    {
        // Create a fake buffer
        $challenge_buffer = new \GameQ\Buffer("\xFF\xFF\xFF\xFF\xFFtest");

        // Apply the challenge
        $this->stub->challengeParseAndApply($challenge_buffer);

        self::assertEquals($this->packets, $this->stub->getPacket());
    }

    /**
     * Test the modern response-driven A2S challenge sequence.
     */
    public function testResponseDrivenChallengeSequence(): void
    {
        $server = new Server([
            Server::SERVER_TYPE => 'source',
            Server::SERVER_HOST => '127.0.0.1:27015',
        ]);
        $this->stub->beforeSend($server);

        self::assertSame(
            [\GameQ\Protocol::PACKET_DETAILS => "\xFF\xFF\xFF\xFFTSource Engine Query\x00"],
            $this->stub->getPacket('!challenge'),
        );

        $this->stub->packetResponse(["\xFF\xFF\xFF\xFF\x41info"]);
        self::assertSame(
            ["\xFF\xFF\xFF\xFFTSource Engine Query\x00info"],
            $this->stub->getFollowUpPackets(),
        );

        $this->stub->appendPacketResponse(["\xFF\xFF\xFF\xFF\x49details"]);
        self::assertSame(
            ["\xFF\xFF\xFF\xFF\x55info"],
            $this->stub->getFollowUpPackets(),
        );

        $this->stub->appendPacketResponse(["\xFF\xFF\xFF\xFF\x41play"]);
        self::assertSame(
            ["\xFF\xFF\xFF\xFF\x55play"],
            $this->stub->getFollowUpPackets(),
        );

        $this->stub->appendPacketResponse(["\xFF\xFF\xFF\xFF\x44players"]);
        self::assertSame(
            ["\xFF\xFF\xFF\xFF\x56play"],
            $this->stub->getFollowUpPackets(),
        );

        $this->stub->appendPacketResponse(["\xFF\xFF\xFF\xFF\x41rule"]);
        self::assertSame(
            ["\xFF\xFF\xFF\xFF\x56rule"],
            $this->stub->getFollowUpPackets(),
        );

        $this->stub->appendPacketResponse(["\xFF\xFF\xFF\xFF\x45rules"]);
        self::assertSame([], $this->stub->getFollowUpPackets());
    }

    public function testPlayersCanBeDisabledWithoutSkippingRules(): void
    {
        $protocol = new \GameQ\Protocols\Source(['query_players' => false]);
        $server = new Server([
            Server::SERVER_TYPE => 'source',
            Server::SERVER_HOST => '127.0.0.1:27015',
        ]);
        $protocol->beforeSend($server);
        $protocol->packetResponse(["\xFF\xFF\xFF\xFF\x49details"]);

        self::assertSame(
            ["\xFF\xFF\xFF\xFF\x56\xFF\xFF\xFF\xFF"],
            $protocol->getFollowUpPackets(),
        );
    }

    /**
     * Test that the legacy WON protocol keeps its own request packets.
     */
    public function testWonPacketsRemainUnchanged(): void
    {
        $protocol = new \GameQ\Protocols\Won();
        $server = new Server([
            Server::SERVER_TYPE => 'cs15',
            Server::SERVER_HOST => '127.0.0.1:27015',
        ]);
        $protocol->beforeSend($server);

        self::assertSame([
            \GameQ\Protocol::PACKET_DETAILS => "\xFF\xFF\xFF\xFFdetails\x00",
            \GameQ\Protocol::PACKET_PLAYERS => "\xFF\xFF\xFF\xFFplayers",
            \GameQ\Protocol::PACKET_RULES => "\xFF\xFF\xFF\xFFrules",
        ], $protocol->getPacket('!challenge'));
        self::assertSame([], $protocol->getFollowUpPackets());
    }

    /**
     * Test invalid packet type without debug
     */
    public function testInvalidPacketType(): void
    {
        // Read in a css source file
        $source = self::fixtureContents(sprintf('%s/Providers/Css/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace("\xFF\xFF\xFF\xFFI", "\xFF\xFF\xFF\xFFX", $source);

        // Should show up as offline
        $testResult = $this->queryTest('127.0.0.1:27015', 'css', explode(PHP_EOL . '||' . PHP_EOL, $source), false);

        self::assertFalse($testResult['gq_online']);
    }

    /**
     * Test for invalid packet type in response
     */
    public function testInvalidPacketTypeDebug(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains("GameQ\Protocols\Source::processResponse response type 'X' is not valid");

        // Read in a css source file
        $source = self::fixtureContents(sprintf('%s/Providers/Css/1_response.txt', __DIR__));

        // Change the first packet to some unknown header
        $source = str_replace("\xFF\xFF\xFF\xFFI", "\xFF\xFF\xFF\xFFX", $source);

        // Should fail out
        $this->queryTest('127.0.0.1:27015', 'css', explode(PHP_EOL . '||' . PHP_EOL, $source), true);
    }

    /**
     * Test compressed split responses, including out-of-order fragments
     */
    #[\PHPUnit\Framework\Attributes\RequiresPhpExtension('bz2')]
    public function testCompressedSplitResponseIsReassembledAndValidated(): void
    {
        $response = "\xFF\xFF\xFF\xFF\x45\x01\x00hostname\x00Example server\x00";
        $compressed = bzcompress($response);

        self::assertIsString($compressed);

        $splitAt = intdiv(strlen($compressed) + 1, 2);
        $chunks = [
            substr($compressed, 0, $splitAt),
            substr($compressed, $splitAt),
        ];
        $packetId = 0x80000001;
        $packets = [
            pack('V', 0xFFFFFFFE)
                . pack('V', $packetId)
                . pack('CCvVV', 2, 0, 1248, strlen($response), crc32($response))
                . $chunks[0],
            pack('V', 0xFFFFFFFE)
                . pack('V', $packetId)
                . pack('CCv', 2, 1, 1248)
                . $chunks[1],
        ];

        $this->stub->packetResponse(array_reverse($packets));

        self::assertSame(
            [
                'num_rules' => 1,
                'hostname' => 'Example server',
            ],
            $this->stub->processResponse(),
        );
    }

    /**
     * Test legacy compressed split responses that omit the maximum packet-size field.
     */
    #[\PHPUnit\Framework\Attributes\RequiresPhpExtension('bz2')]
    public function testCompressedSplitResponseWithoutPacketSizeIsReassembled(): void
    {
        $response = "\xFF\xFF\xFF\xFF\x45\x01\x00hostname\x00Example server\x00";
        $compressed = bzcompress($response);

        self::assertIsString($compressed);

        $splitAt = intdiv(strlen($compressed) + 1, 2);
        $chunks = [
            substr($compressed, 0, $splitAt),
            substr($compressed, $splitAt),
        ];
        $packetId = 0x80000001;
        $packets = [
            pack('V', 0xFFFFFFFE)
                . pack('V', $packetId)
                . pack('CCVV', 2, 0, strlen($response), crc32($response))
                . $chunks[0],
            pack('V', 0xFFFFFFFE)
                . pack('V', $packetId)
                . pack('CC', 2, 1)
                . $chunks[1],
        ];

        $this->stub->packetResponse(array_reverse($packets));

        self::assertSame(
            [
                'num_rules' => 1,
                'hostname' => 'Example server',
            ],
            $this->stub->processResponse(),
        );
    }

    /**
     * Test the packed GoldSource split count/index byte used by Counter-Strike 1.6.
     */
    public function testGoldSourceSplitResponseIsReassembled(): void
    {
        $response = "\xFF\xFF\xFF\xFF\x45\x01\x00hostname\x00Example server\x00";
        $chunks = str_split($response, max(1, intdiv(strlen($response) + 1, 2)));
        self::assertCount(2, $chunks);

        $packets = [
            pack('V', 0xFFFFFFFE) . pack('V', 1234) . "\x02" . $chunks[0],
            pack('V', 0xFFFFFFFE) . pack('V', 1234) . "\x12" . $chunks[1],
        ];
        $protocol = new \GameQ\Protocols\Cs16();
        $protocol->packetResponse(array_reverse($packets));

        self::assertSame([
            'num_rules' => 1,
            'hostname' => 'Example server',
        ], $protocol->processResponse());
    }

    /**
     * Test the Source 2006 split format that omits the maximum packet-size field.
     */
    public function testSource2006SplitResponseWithoutPacketSizeIsReassembled(): void
    {
        $details = "\xFF\xFF\xFF\xFF\x49\x07"
            . "Source 2006 Server\x00de_dust2\x00cstrike\x00Counter-Strike: Source\x00"
            . pack('vCCC', 240, 1, 32, 0)
            . "dl"
            . pack('CC', 0, 1)
            . "1.0.0.0\x00";
        $rules = "\xFF\xFF\xFF\xFF\x45\x01\x00hostname\x00Example server\x00";
        $chunks = str_split($rules, max(1, intdiv(strlen($rules) + 1, 2)));
        self::assertCount(2, $chunks);

        $packets = [
            $details,
            pack('V', 0xFFFFFFFE) . pack('V', 1234) . pack('CC', 2, 1) . $chunks[1],
            pack('V', 0xFFFFFFFE) . pack('V', 1234) . pack('CC', 2, 0) . $chunks[0],
        ];
        $protocol = new \GameQ\Protocols\Css();
        $protocol->packetResponse($packets);

        $result = $protocol->processResponse();

        self::assertSame('Counter-Strike: Source', $result['game_descr']);
        self::assertSame('Example server', $result['hostname']);
        self::assertSame(1, $result['num_rules']);
    }
}
