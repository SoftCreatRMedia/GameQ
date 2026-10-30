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

use GameQ\Filters\Normalize;
use GameQ\Protocol;
use GameQ\Server;
use GameQ\Tests\TestBase;
use PHPUnit\Framework\Attributes\DataProvider;

class Subnautica2 extends TestBase
{
    /** @return iterable<string, array{string, int, int}> */
    public static function captures(): iterable
    {
        yield 'empty open host' => ['empty', 0, 0];
        yield 'real client on open host' => ['populated', 1, 0];
        yield 'empty password-advertising host' => ['passworded', 0, 1];
        yield 'real client on password-advertising host' => ['passworded-populated', 1, 1];
    }

    #[DataProvider('captures')]
    public function testCapturedResponses(string $label, int $count, int $password): void
    {
        $server = new Server(['type' => 'subnautica2', 'host' => '127.0.0.1:27015']);
        $result = (new Normalize())->apply(
            $this->queryTest('127.0.0.1:27015', 'subnautica2', self::capturePackets($label)),
            $server,
        );
        self::assertTrue($result['gq_online']);
        self::assertSame('GameQ Subnautica 2 Test', $result['gq_hostname']);
        self::assertSame($count, $result['gq_numplayers']);
        self::assertSame(4, $result['gq_maxplayers']);
        self::assertSame($password, $result['gq_password']);
        self::assertSame('d', $result['gq_dedicated']);
        self::assertSame('Subnautica 2', $result['gq_gametype']);
        self::assertSame('Beacon', $result['gq_mod']);
        self::assertSame('Awake', $result['gq_mapname']);
        self::assertSame(27017, $result['gq_port_query']);
        self::assertSame('', $result['gq_joinlink']);
        self::assertSame(62156, $result['steamappid']);
        self::assertSame(1962700, $result['game_id']);
        self::assertSame('beacon-0.3.135/sn2-Subnautica2-CL-131439', $result['version']);
        self::assertSame('beacon,sn2,beacon=0.3.135,sn2build=Subnautica2-CL-131439', $result['keywords']);
        self::assertSame('gameq-20261023', $result['instance']);
        self::assertSame('27015', $result['gameplay_port']);
        self::assertSame('0.3.135', $result['beacon_version']);
        self::assertSame('Subnautica2-CL-131439', $result['sn2_build']);
        self::assertSame(4, $result['num_rules']);
        self::assertSame([], $result['teams']);
        self::assertIsArray($result['players']);
        self::assertCount($count, $result['players']);

        if ($count === 1) {
            self::assertIsArray($result['players'][0]);
            self::assertSame('GameQ_Test_Player', $result['players'][0]['gq_name']);
            self::assertSame(0, $result['players'][0]['gq_score']);
            self::assertGreaterThan(0, $result['players'][0]['gq_time']);
        }
    }

    public function testProtocolSettingsAndPortOverride(): void
    {
        $server = new Server(['type' => 'subnautica2', 'host' => '127.0.0.1:27015']);
        self::assertSame(27017, $server->portQuery());
        self::assertNull($server->getJoinLink());
        self::assertSame('source', $server->protocolInstance()->getProtocol());
        self::assertSame(Protocol::STATE_BETA, $server->protocolInstance()->state());
        $override = new Server([
            'type' => 'subnautica2',
            'host' => '127.0.0.1:27015',
            'options' => ['query_port' => 28017],
        ]);
        self::assertSame(28017, $override->portQuery());
    }

    public function testStaleGameHeartbeatIsOffline(): void
    {
        self::assertSame([], self::capturePackets('stale-heartbeat'));
        self::assertFalse($this->queryTest('127.0.0.1:27015', 'subnautica2', [])['gq_online']);
    }

    /** @return iterable<string, array{string}> */
    public static function malformedPackets(): iterable
    {
        yield 'truncated info' => ["\xff\xff\xff\xffI\x11"];
        yield 'unterminated hostname' => ["\xff\xff\xff\xffI\x11Unterminated"];
        yield 'unterminated player name' => ["\xff\xff\xff\xffD\x01\x00Player"];
        yield 'truncated rule count' => ["\xff\xff\xff\xffE\x01"];
    }

    #[DataProvider('malformedPackets')]
    public function testMalformedResponsesAreOffline(string $packet): void
    {
        self::assertFalse($this->queryTest('127.0.0.1:27015', 'subnautica2', [$packet])['gq_online']);
    }

    public function testTruncatedFullGameIdIsOffline(): void
    {
        $info = self::capturePackets('empty')[0];
        self::assertFalse($this->queryTest('127.0.0.1:27015', 'subnautica2', [substr($info, 0, -1)])['gq_online']);
    }

    public function testSourceCountOnlyPlayerReplyDoesNotInventEntries(): void
    {
        // Source compatibility permits responders that expose only the count.
        $result = $this->queryTest('127.0.0.1:27015', 'subnautica2', ["\xff\xff\xff\xffD\x02"]);
        self::assertTrue($result['gq_online']);
        self::assertSame(2, $result['num_players']);
        self::assertArrayNotHasKey('players', $result);
    }

    /** @return list<string> */
    private static function capturePackets(string $label): array
    {
        $contents = self::fixtureContents(__DIR__ . '/Providers/Subnautica2/' . $label . '.json');
        $capture = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        self::assertIsArray($capture);
        $packets = [];

        foreach (['info', 'players', 'rules'] as $stage) {
            self::assertIsArray($capture[$stage]);

            foreach ($capture[$stage] as $hex) {
                self::assertIsString($hex);
                $packet = hex2bin($hex);
                self::assertIsString($packet);
                $packets[] = $packet;
            }
        }

        return $packets;
    }
}
