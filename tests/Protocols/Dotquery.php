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
 */

namespace GameQ\Tests\Protocols;

use GameQ\Buffer;
use GameQ\Protocol;
use GameQ\Protocols\Dotquery as DotqueryProtocol;
use GameQ\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class Dotquery extends TestCase
{
    private const COOKIE = "\x01\x02\x03\x04\x05\x06\x07\x08";

    /** @param array<string, mixed> $options */
    private function server(array $options = []): Server
    {
        $server = new Server(['type' => 'dotquery', 'host' => '127.0.0.1:28766', 'options' => $options]);
        $server->protocolInstance()->beforeSend($server);

        return $server;
    }

    private function transaction(Protocol $protocol): int
    {
        $request = $protocol->getPacket(Protocol::PACKET_DETAILS);
        self::assertIsString($request);

        return (new Buffer(substr($request, 6, 4)))->readInt32();
    }

    private function packet(
        Protocol $protocol,
        string $body,
        int $type = 0x81,
        int $flags = 0,
        int $count = 1,
        int $index = 0,
        int $response = 1
    ): string {
        return 'DQP1' . pack('CCVVvv', $type, $flags, $this->transaction($protocol), $response, $count, $index)
            . self::COOKIE . $body;
    }

    private function challenge(Protocol $protocol): void
    {
        $protocol->packetResponse([$this->packet($protocol, '', 0x82)]);
        $followUp = $protocol->getFollowUpPackets();
        self::assertCount(1, $followUp);
        self::assertSame(self::COOKIE, substr($followUp[0], 18, 8));
        self::assertSame([], $protocol->getFollowUpPackets());
    }

    /**
     * @return array{rev: int, ts: int, sections: array{
     *     info: array<string, mixed>, players: list<array<string, mixed>>,
     *     rules: array<string, string>, game: array<string, int>, teams: array<string, mixed>
     * }}
     */
    private static function body(): array
    {
        return ['rev' => 7, 'ts' => 1792790000, 'sections' => [
            'info' => ['name' => 'Überlebende 日本', 'map' => 'bhop_g2g_intro', 'players' => 1,
                'max_players' => 16, 'server_type' => 'dedicated', 'password' => true, 'app' => 'g2gfast', 'version' => '0.1.0'],
            'players' => [['name' => 'Björk', 'score' => 8, 'duration' => 14.5, 'userid' => 1]],
            'rules' => ['sv_tickrate' => '100'], 'game' => ['tick_rate' => 100],
            'teams' => ['Blue' => ['players' => 1], '_balanced' => true],
        ]];
    }

    public function testMetadataPortsAndSafeDefaultSections(): void
    {
        $server = $this->server();
        $protocol = $server->protocolInstance();
        self::assertSame(28766, $server->portQuery());
        self::assertSame(27099, $this->server(['query_port' => 27099])->portQuery());
        self::assertSame(Protocol::STATE_BETA, $protocol->state());
        self::assertNull($server->getJoinLink());
        $request = $protocol->getPacket(Protocol::PACKET_DETAILS);
        self::assertIsString($request);
        self::assertSame(4, ord($request[5]));
        self::assertSame(str_repeat("\0", 8), substr($request, 18, 8));
        self::assertSame(
            ['sections' => ['info', 'players', 'rules', 'game', 'teams', 'rotation', 'build']],
            json_decode(substr($request, 26), true, 64, JSON_THROW_ON_ERROR)
        );

        $options = ['query_gzip' => false, 'query_perf' => true, 'query_stats' => true, 'query_player_stats' => true];
        $request = $this->server($options)->protocolInstance()->getPacket(Protocol::PACKET_DETAILS);
        self::assertIsString($request);
        self::assertSame(0, ord($request[5]));
        self::assertStringContainsString('"perf","stats","player_stats"', $request);
    }

    public function testChallengeAndPlainResponse(): void
    {
        $protocol = $this->server()->protocolInstance();
        $this->challenge($protocol);
        $protocol->appendPacketResponse([$this->packet($protocol, json_encode(self::body(), JSON_THROW_ON_ERROR))]);
        $result = $protocol->processResponse();
        self::assertSame('Überlebende 日本', $result['hostname']);
        self::assertSame(1, $result['num_players']);
        self::assertTrue($result['dedicated']);
        self::assertTrue($result['password']);
        self::assertSame(self::body()['sections'], $result['sections']);
        self::assertSame([['name' => 'Blue', 'players' => 1]], $result['teams']);
    }

    public function testGzipAndOutOfOrderFragmentAssembly(): void
    {
        $protocol = $this->server()->protocolInstance();
        $body = self::body();
        $body['padding'] = str_repeat('field-', 900);
        $json = json_encode($body, JSON_THROW_ON_ERROR);
        foreach ([false, true] as $gzip) {
            $protocol->beforeSend($this->server());
            $this->challenge($protocol);
            $payload = $gzip ? gzencode($json) : $json;
            self::assertIsString($payload);
            $chunks = str_split($payload, 1174);
            foreach (array_reverse(array_keys($chunks)) as $index) {
                $flags = ($gzip ? 1 : 0) | (count($chunks) > 1 ? 2 : 0);
                $protocol->appendPacketResponse([$this->packet($protocol, $chunks[$index], 0x81, $flags, count($chunks), $index)]);
            }
            self::assertSame($body['padding'], $protocol->processResponse()['padding']);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function badHeaders(): iterable
    {
        foreach (['short', 'magic', 'txn', 'type', 'flags', 'count_zero', 'count_large', 'index', 'cookie', 'oversize'] as $case) {
            yield $case => [$case];
        }
    }

    #[DataProvider('badHeaders')]
    public function testMalformedResponsesAreRejected(string $case): void
    {
        $protocol = $this->server()->protocolInstance();
        $this->challenge($protocol);
        $packet = $this->packet($protocol, json_encode(self::body(), JSON_THROW_ON_ERROR));
        $packet = match ($case) {
            'short' => substr($packet, 0, 25),
            'magic' => 'BAD!' . substr($packet, 4),
            'txn' => substr_replace($packet, pack('V', $this->transaction($protocol) ^ 1), 6, 4),
            'type' => substr_replace($packet, "\x83", 4, 1),
            'flags' => substr_replace($packet, "\x04", 5, 1),
            'count_zero' => substr_replace($packet, "\0\0", 14, 2),
            'count_large' => substr_replace($packet, pack('v', 17), 14, 2),
            'index' => substr_replace($packet, pack('v', 1), 16, 2),
            'cookie' => substr_replace($packet, str_repeat("\0", 8), 18, 8),
            'oversize' => $packet . str_repeat('x', 1201),
            default => $packet,
        };
        $protocol->appendPacketResponse([$packet]);
        self::assertSame([], $protocol->processResponse());
    }

    public function testChallengeFailuresAndTimeouts(): void
    {
        $protocol = $this->server()->protocolInstance();
        self::assertSame([], $protocol->getFollowUpPackets());
        self::assertSame([], $protocol->processResponse());
        foreach (['', str_repeat("\0", 8)] as $cookie) {
            $packet = substr($this->packet($protocol, '', 0x82), 0, 18) . $cookie;
            $protocol->packetResponse([$packet]);
            self::assertSame([], $protocol->getFollowUpPackets());
        }
        $protocol->packetResponse([$this->packet($protocol, '{}', 0x82)]);
        self::assertSame([], $protocol->getFollowUpPackets());
        $this->challenge($protocol);
        self::assertSame([], $protocol->processResponse());
    }

    public function testMissingDuplicateConflictingAndUnflaggedFragments(): void
    {
        foreach (['missing', 'duplicate', 'conflict', 'unflagged'] as $case) {
            $protocol = $this->server()->protocolInstance();
            $this->challenge($protocol);
            $parts = str_split(json_encode(self::body(), JSON_THROW_ON_ERROR), 200);
            foreach ($parts as $index => $part) {
                if ($case === 'missing' && $index === 0) {
                    continue;
                }
                $packet = $this->packet(
                    $protocol,
                    $part,
                    0x81,
                    $case === 'unflagged' ? 0 : 2,
                    count($parts),
                    $index,
                    $case === 'conflict' && $index === 0 ? 2 : 1
                );
                $protocol->appendPacketResponse([$packet]);
                if ($case === 'duplicate' && $index === 0) {
                    $protocol->appendPacketResponse([$packet]);
                }
            }
            self::assertSame([], $protocol->processResponse(), $case);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function badBodies(): iterable
    {
        foreach (['[]', 'null', '{', '"text"', '{"invalid":"' . "\xff" . '"}'] as $index => $body) {
            yield 'json-' . $index => [$body];
        }
        foreach (['rev' => '1', 'ts' => -1, 'sections' => null] as $key => $value) {
            $body = self::body();
            $body[$key] = $value;
            yield $key => [json_encode($body, JSON_THROW_ON_ERROR)];
        }
        foreach (['name' => [], 'players' => '1', 'max_players' => 0, 'password' => 1, 'map' => false] as $key => $value) {
            $body = self::body();
            $body['sections']['info'][$key] = $value;
            yield 'info-' . $key => [json_encode($body, JSON_THROW_ON_ERROR)];
        }
        foreach (
            ['players' => 'bad', 'rules' => false, 'teams' => [false], 'game' => 1,
            'players-score' => [['name' => 'X', 'score' => '1']],
            'players-duration' => [['name' => 'X', 'duration' => -1]],
            'players-many' => array_fill(0, 129, ['name' => 'X']),
            ] as $key => $value
        ) {
            $body = self::body();
            $body['sections'][str_starts_with($key, 'players-') ? 'players' : $key] = $value;
            yield 'section-' . $key => [json_encode($body, JSON_THROW_ON_ERROR)];
        }
    }

    #[DataProvider('badBodies')]
    public function testInvalidJsonAndFieldTypesAreRejected(string $body): void
    {
        $protocol = $this->server()->protocolInstance();
        $this->challenge($protocol);
        $protocol->appendPacketResponse([$this->packet($protocol, $body)]);
        self::assertSame([], $protocol->processResponse());
    }

    public function testGzipLimitsAndUnsolicitedCompression(): void
    {
        foreach ([['query_gzip' => false], []] as $options) {
            $protocol = $this->server($options)->protocolInstance();
            $this->challenge($protocol);
            $payload = gzencode(str_repeat('x', 1048577));
            self::assertIsString($payload);
            $protocol->appendPacketResponse([$this->packet($protocol, $payload, 0x81, 1)]);
            self::assertSame([], $protocol->processResponse());
        }
    }

    public function testConditionalResponsesUseOnlyTheSameEndpointAndSections(): void
    {
        $server = $this->server(['query_conditional' => true]);
        $protocol = $server->protocolInstance();
        $this->challenge($protocol);
        $protocol->appendPacketResponse([$this->packet($protocol, json_encode(self::body(), JSON_THROW_ON_ERROR))]);
        self::assertSame(1, $protocol->processResponse()['num_players']);
        $protocol->beforeSend($server);
        $request = $protocol->getPacket(Protocol::PACKET_DETAILS);
        self::assertIsString($request);
        self::assertStringContainsString('"if_rev":7', $request);
        $this->challenge($protocol);
        $protocol->appendPacketResponse([$this->packet($protocol, '{"rev":7,"unchanged":true}')]);
        self::assertSame(1, $protocol->processResponse()['num_players']);

        $protocol->beforeSend($this->server(['query_port' => 27099]));
        $request = $protocol->getPacket(Protocol::PACKET_DETAILS);
        self::assertIsString($request);
        self::assertStringNotContainsString('if_rev', $request);
        $this->challenge($protocol);
        $protocol->appendPacketResponse([$this->packet($protocol, '{"rev":7,"unchanged":true}')]);
        self::assertSame([], $protocol->processResponse());
    }

    public function testCapturedRealGameDatagrams(): void
    {
        foreach (['empty-plain', 'empty-gzip', 'populated-plain', 'populated-gzip', 'quota-plain', 'quota-gzip'] as $label) {
            $contents = file_get_contents(__DIR__ . '/Providers/Dotquery/' . $label . '.json');
            self::assertIsString($contents);
            $capture = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
            self::assertIsArray($capture);
            self::assertIsArray($capture['responses']);
            self::assertIsArray($capture['body']);
            $protocol = $this->server()->protocolInstance();
            $this->challenge($protocol);

            foreach ($capture['responses'] as $encoded) {
                self::assertIsString($encoded);
                $packet = base64_decode($encoded, true);
                self::assertIsString($packet);
                // Only replace transaction/cookie to replay this capture in the
                // new query. The responder's type, flags, fragments and body stay exact.
                $packet = substr_replace($packet, pack('V', $this->transaction($protocol)), 6, 4);
                $packet = substr_replace($packet, self::COOKIE, 18, 8);
                $protocol->appendPacketResponse([$packet]);
            }

            $result = $protocol->processResponse();
            if ($label === 'quota-plain') {
                self::assertSame([], $result);
            } else {
                self::assertSame($capture['body']['sections'], $result['sections'], $label);
                self::assertSame('g2gfast test', $result['hostname']);
                self::assertSame(str_starts_with($label, 'populated') ? 1 : 0, $result['num_players']);
            }
        }
    }

    public function testNumericJsonTeamIdentifiersArePreserved(): void
    {
        $body = self::body();
        $body['sections']['teams'] = [0 => ['players' => 1], 1 => ['players' => 0], '_balanced' => false];
        $protocol = $this->server()->protocolInstance();
        $this->challenge($protocol);
        $protocol->appendPacketResponse([$this->packet($protocol, json_encode($body, JSON_THROW_ON_ERROR))]);
        self::assertSame(
            [['name' => '0', 'players' => 1], ['name' => '1', 'players' => 0]],
            $protocol->processResponse()['teams']
        );
    }
}
