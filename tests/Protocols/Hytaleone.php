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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace GameQ\Tests\Protocols;

use GameQ\Buffer;
use RuntimeException;

class Hytaleone extends Base
{
    /**
     * @param list<string> $responses
     * @param non-empty-array<string, array<string, mixed>> $result
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('loadData')]
    public function testResponses(array $responses, array $result): void
    {
        $server = self::firstServerKey($result);
        $testResult = $this->queryTest($server, 'hytaleone', $responses);

        self::assertEquals($result[$server], $testResult);
    }

    public function testV2ChallengeAuthenticationAndPagination(): void
    {
        $protocol = new \GameQ\Protocols\Hytaleone(['auth_token' => 'secret']);
        $challengeToken = str_repeat("\xAB", 32);
        $challenge = new Buffer('ONEREPLY' . "\x00" . $challengeToken . str_repeat("\x00", 7));

        self::assertTrue($protocol->challengeParseAndApply($challenge));
        self::assertSame(\GameQ\Protocol::STATE_STABLE, $protocol->state());

        $packets = $protocol->getPacket('!challenge');
        self::assertIsArray($packets);
        self::assertArrayHasKey(\GameQ\Protocol::PACKET_BASIC, $packets);
        self::assertArrayHasKey(\GameQ\Protocol::PACKET_PLAYERS, $packets);

        $playerQuery = $packets[\GameQ\Protocol::PACKET_PLAYERS];
        self::assertSame('ONEQUERY', substr($playerQuery, 0, 8));
        self::assertSame("\x02", $playerQuery[8]);
        self::assertSame($challengeToken, substr($playerQuery, 9, 32));
        self::assertSame(1, self::unpackInt16(substr($playerQuery, 45, 2)));
        self::assertSame('secret', substr($playerQuery, 53));

        $serverInfo = self::string16('Hytale Test')
            . self::string16('Welcome')
            . pack('V', 2)
            . pack('V', 100)
            . self::string16('2026.07.30')
            . pack('V', 7)
            . self::string16('DEADBEEF')
            . self::string16('play.example.test')
            . pack('v', 5520);

        $basicResponse = self::v2Packet(0x0020, 1, self::tlv(0x0001, $serverInfo));
        $firstPage = self::playerPage(2, 0, [
            ['Alice', '11111111-2222-3333-4444-555555555555'],
        ]);
        $firstPlayerResponse = self::v2Packet(0x0001, 2, self::tlv(0x0002, $firstPage));

        $protocol->packetResponse([$basicResponse, $firstPlayerResponse]);
        $followUp = $protocol->getFollowUpPackets();

        self::assertCount(1, $followUp);
        self::assertSame(1, self::unpackInt32(substr($followUp[0], 47, 4)));

        $secondPage = self::playerPage(2, 1, [
            ['Bob', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'],
        ]);
        $protocol->appendPacketResponse([self::v2Packet(0, 3, self::tlv(0x0002, $secondPage))]);

        self::assertSame([], $protocol->getFollowUpPackets());
        self::assertSame([
            'onequery_version' => 1,
            'hostname' => 'Hytale Test',
            'motd' => 'Welcome',
            'num_players' => 2,
            'max_players' => 100,
            'version' => '2026.07.30',
            'protocol_version' => 7,
            'protocol_hash' => 'DEADBEEF',
            'host' => 'play.example.test',
            'port' => 5520,
            'players' => [
                ['name' => 'Alice', 'uuid' => '11111111-2222-3333-4444-555555555555'],
                ['name' => 'Bob', 'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'],
            ],
            'players_total' => 2,
            'players_returned' => 2,
            'players_truncated' => false,
        ], $protocol->processResponse());
    }

    public function testMalformedV2ChallengeIsRejected(): void
    {
        $protocol = new \GameQ\Protocols\Hytaleone();
        $challenge = new Buffer('ONEREPLY' . "\x00" . str_repeat("\xAB", 32) . str_repeat("\x01", 7));

        self::assertFalse($protocol->challengeParseAndApply($challenge));
        self::assertSame("HYQUERY\x00\x01", $protocol->getPacket(\GameQ\Protocol::PACKET_ALL));
    }

    public function testCurrentV1CapabilityMetadataIsParsed(): void
    {
        $response = self::fixtureContents(__DIR__ . '/Providers/Hytaleone/2_response.txt')
            . pack('vC', 0x0003, 1);
        $protocol = new \GameQ\Protocols\Hytaleone();
        $protocol->packetResponse([$response]);
        $result = $protocol->processResponse();

        self::assertSame(3, $result['onequery_capabilities']);
        self::assertSame(1, $result['onequery_v2_version']);
        self::assertTrue($result['network']);
    }

    private static function string16(string $value): string
    {
        return pack('v', strlen($value)) . $value;
    }

    private static function tlv(int $type, string $value): string
    {
        return pack('v', $type) . pack('v', strlen($value)) . $value;
    }

    private static function v2Packet(int $flags, int $requestId, string $payload): string
    {
        return 'ONEREPLY'
            . "\x01"
            . pack('v', $flags)
            . pack('V', $requestId)
            . pack('v', strlen($payload))
            . $payload;
    }

    /**
     * @param list<array{string, string}> $players
     */
    private static function playerPage(int $total, int $offset, array $players): string
    {
        $page = pack('V', $total) . pack('V', count($players)) . pack('V', $offset);

        foreach ($players as [$name, $uuid]) {
            $uuidBytes = hex2bin(str_replace('-', '', $uuid));

            if ($uuidBytes === false) {
                throw new RuntimeException("Invalid test UUID '$uuid'.");
            }

            $page .= self::string16($name) . $uuidBytes;
        }

        return $page;
    }

    private static function unpackInt16(string $value): int
    {
        $unpacked = unpack('vvalue', $value);

        $integer = $unpacked['value'] ?? null;

        if (!is_int($integer)) {
            throw new RuntimeException('Unable to unpack the test integer.');
        }

        return $integer;
    }

    private static function unpackInt32(string $value): int
    {
        $unpacked = unpack('Vvalue', $value);

        $integer = $unpacked['value'] ?? null;

        if (!is_int($integer)) {
            throw new RuntimeException('Unable to unpack the test integer.');
        }

        return $integer;
    }
}
