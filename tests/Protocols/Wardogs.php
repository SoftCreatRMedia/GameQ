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

use GameQ\Exception\ProtocolException;
use GameQ\Exception\QueryException;
use GameQ\Exception\ServerException;
use GameQ\GameQ;

/**
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Wardogs extends Base
{
    /**
     * @throws QueryException
     * @throws ServerException
     * @throws ProtocolException
     */
    public function testApiResponsesAreParsedWithoutNetworkAccess(): void
    {
        $gameQ = new GameQ();
        $gameQ->addServer([
            'id' => 'wardogs-test',
            'type' => 'wardogs',
            'host' => '127.0.0.1:7777',
            'options' => [
                'api_responses' => [
                    'status' => [
                        'serverName' => 'Friday Night WARDOGS',
                        'map' => 'WD_Forest',
                        'experiences' => ['Conquest', 'Infantry'],
                        'lighting' => 'Day',
                        'alternator' => 'Fixed',
                        'scoreTick' => ['current' => 2.5, 'min' => 1, 'max' => 5],
                        'scoreCap' => 500,
                        'matchSeconds' => 1_245,
                        'players' => ['current' => 2, 'max' => 64],
                        'factionScores' => [
                            ['name' => 'Blue', 'colorHex' => '#3366ff', 'score' => 240],
                            ['name' => 'Red', 'colorHex' => '#ff3333', 'score' => 217],
                        ],
                        'rotation' => ['nowIndex' => 1, 'nextIndex' => 2],
                    ],
                    'players' => [
                        'players' => [
                            [
                                'name' => 'Alice',
                                'steamId' => '76561198000000001',
                                'faction' => 'Blue',
                                'kills' => 7,
                                'deaths' => 2,
                                'cash' => 120,
                                'pingMs' => 31,
                            ],
                            [
                                'name' => 'Bob',
                                'steamId' => '76561198000000002',
                                'faction' => 'Red',
                                'kills' => 4,
                                'deaths' => 5,
                                'cash' => 80,
                                'pingMs' => 48,
                            ],
                        ],
                        'count' => 2,
                    ],
                    'capabilities' => [
                        'apiVersion' => 1,
                        'build' => '2026.09.17',
                        'routes' => ['GET /v1/status', 'GET /v1/players'],
                    ],
                    'health' => [
                        'status' => 'ok',
                        'uptimeSeconds' => 3_600,
                        'connections' => ['active' => 2],
                        'gameThreadQueue' => [
                            'inFlight' => 1,
                            'depth' => 3,
                            'rejectedTotal' => 0,
                        ],
                    ],
                    'server-id' => ['serverId' => 'WD-TEST-1234'],
                ],
            ],
        ]);
        $result = $gameQ->process()['wardogs-test'];

        self::assertTrue($result['gq_online']);
        self::assertSame('Friday Night WARDOGS', $result['gq_hostname']);
        self::assertSame('WD_Forest', $result['gq_mapname']);
        self::assertSame(2, $result['gq_numplayers']);
        self::assertSame(64, $result['gq_maxplayers']);
        self::assertSame(['Conquest', 'Infantry'], $result['experiences']);
        self::assertSame(1, $result['api_version']);
        self::assertSame('WD-TEST-1234', $result['server_id']);
        self::assertSame(2, $result['connections_active']);
        self::assertSame(3, $result['game_thread_queue_depth']);

        $players = $result['players'] ?? null;
        self::assertIsArray($players);
        self::assertCount(2, $players);
        $firstPlayer = $players[0] ?? null;
        self::assertIsArray($firstPlayer);
        self::assertSame('Alice', $firstPlayer['gq_name'] ?? null);
        self::assertSame(31, $firstPlayer['gq_ping'] ?? null);
        self::assertSame('76561198000000001', $firstPlayer['steam_id'] ?? null);

        $teams = $result['teams'] ?? null;
        self::assertIsArray($teams);
        self::assertCount(2, $teams);
        $firstTeam = $teams[0] ?? null;
        self::assertIsArray($firstTeam);
        self::assertSame('Blue', $firstTeam['gq_name'] ?? null);
        self::assertSame(240.0, $firstTeam['gq_score'] ?? null);
    }

    /**
     * @throws \ReflectionException
     * @throws ServerException
     */
    public function testIncompleteStatusResponseIsOffline(): void
    {
        $result = $this->queryTest(
            '127.0.0.1:7777',
            'wardogs',
            [],
            false,
            ['api_responses' => ['status' => ['players' => ['current' => 0, 'max' => 64]]]],
        );

        self::assertFalse($result['gq_online']);
    }

    /**
     * @throws \ReflectionException
     * @throws ServerException
     */
    public function testQueryPortIsRequired(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains("GameQ\\Protocols\\Wardogs::beforeSend Missing required setting 'query_port'.");

        $this->queryTest(
            '127.0.0.1:7777',
            'wardogs',
            [],
            false,
            ['rcon_password' => 'secret'],
        );
    }

    /**
     * @throws \ReflectionException
     * @throws ServerException
     */
    public function testRconPasswordIsRequired(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains(
            "GameQ\\Protocols\\Wardogs::beforeSend Missing required setting 'rcon_password'.",
        );

        $this->queryTest(
            '127.0.0.1:7777',
            'wardogs',
            [],
            false,
            ['query_port' => 7776],
        );
    }
}
