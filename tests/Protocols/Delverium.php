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

class Delverium extends TestBase
{
    /** @return iterable<string, array{string, int}> */
    public static function captures(): iterable
    {
        yield 'empty open server' => ['empty', 0];
        yield 'real client spawned' => ['populated', 1];
        yield 'password configured but A2S flag unreliable' => ['passworded', 0];
        yield 'password accepted by a real client' => ['passworded-populated', 1];
    }

    #[DataProvider('captures')]
    public function testCapturedResponses(string $label, int $count): void
    {
        $contents = self::fixtureContents(__DIR__ . '/Providers/Delverium/' . $label . '.json');
        $capture = json_decode(ltrim($contents, "\xef\xbb\xbf"), true, 64, JSON_THROW_ON_ERROR);
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

        $server = new Server(['type' => 'delverium', 'host' => '127.0.0.1:27016']);
        $result = (new Normalize())->apply($this->queryTest('127.0.0.1:27016', 'delverium', $packets), $server);
        self::assertTrue($result['gq_online']);
        self::assertSame('GameQ Delverium Demo Test', $result['gq_hostname']);
        self::assertSame($count, $result['gq_numplayers']);
        self::assertSame(8, $result['gq_maxplayers']);
        self::assertSame(27017, $result['gq_port_query']);
        self::assertSame('', $result['gq_joinlink']);
        self::assertSame('0.2.6', $result['version']);
        self::assertSame(3426800, $result['game_id']);
        self::assertSame('lodestone,0.2.6,build=0,app=3426800,label=2.35', $result['keywords']);
        self::assertSame('Lodestone', $result['map']);
        self::assertSame('1', $result['DF']);
        self::assertSame('0', $result['WS']);
        self::assertSame('0', $result['HM']);
        self::assertSame('0', $result['MO']);
        self::assertSame(0, $result['password']);
        self::assertSame(0, $result['gq_password']);
        self::assertIsArray($result['players']);
        self::assertCount($count, $result['players']);
        if ($count === 1) {
            self::assertIsArray($result['players'][0]);
            self::assertSame('GameQ Test Play', $result['players'][0]['gq_name']);
            self::assertSame(0, $result['players'][0]['gq_score']);
            self::assertGreaterThan(0, $result['players'][0]['gq_time']);
        }
    }

    public function testPortsStateAndUnsupportedJoinLink(): void
    {
        $server = new Server(['type' => 'delverium', 'host' => '127.0.0.1:27016']);
        self::assertSame(27017, $server->portQuery());
        self::assertNull($server->getJoinLink());
        self::assertSame(Protocol::STATE_BETA, $server->protocolInstance()->state());
        $server = new Server(['type' => 'delverium', 'host' => '127.0.0.1:27016', 'options' => ['query_port' => 28017]]);
        self::assertSame(28017, $server->portQuery());
    }

    public function testNoResponseIsOffline(): void
    {
        self::assertFalse($this->queryTest('127.0.0.1:27016', 'delverium', [])['gq_online']);
    }
}
