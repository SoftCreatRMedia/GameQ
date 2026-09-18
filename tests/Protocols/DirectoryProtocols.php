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

use GameQ\Exception\ServerException;
use ReflectionException;

/**
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class DirectoryProtocols extends Base
{
    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testLuantiOfficialDirectory(): void
    {
        $result = $this->queryTest('127.0.0.1:30000', 'luanti', [], false, [
            'directory_response' => ['list' => [[
                'address' => 'play.example.test',
                'port' => 30000,
                'name' => 'Luanti World',
                'clients' => 2,
                'clients_max' => 50,
                'clients_list' => ['Alice', 'Bob'],
                'version' => '5.11.0',
                'gameid' => 'minetest',
            ]]],
            'directory_address' => 'play.example.test',
        ]);

        self::assertTrue($result['gq_online']);
        self::assertSame('Luanti World', $result['name']);
        self::assertSame(2, $result['clients']);
        self::assertIsArray($result['players']);
        self::assertIsArray($result['players'][0]);
        self::assertSame('Alice', $result['players'][0]['name']);
    }

    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testVintageStoryOfficialDirectory(): void
    {
        $result = $this->queryTest('127.0.0.1:42420', 'vintagestory', [], false, [
            'directory_response' => ['status' => 'ok', 'data' => [[
                'serverName' => 'Vintage Test',
                'serverIP' => 'play.example.test:42420',
                'playstyle' => ['id' => 'surviveandbuild'],
                'mods' => [['id' => 'test', 'version' => '1.0.0']],
                'maxPlayers' => '16',
                'players' => 3,
                'gameVersion' => '1.21.0',
                'hasPassword' => true,
            ]]],
            'directory_address' => 'play.example.test',
        ]);

        self::assertTrue($result['gq_online']);
        self::assertSame('Vintage Test', $result['serverName']);
        self::assertSame(16, $result['maxPlayers']);
        self::assertSame(3, $result['players']);
    }

    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testRenegadeXOfficialDirectory(): void
    {
        $result = $this->queryTest('127.0.0.1:7777', 'renegadex', [], false, [
            'directory_response' => [[
                'Name' => 'Test Server',
                'NamePrefix' => '[EU]',
                'Current Map' => 'CNC-Walls',
                'Bots' => 4,
                'Players' => 12,
                'Game Version' => '5.91',
                'Port' => 7777,
                'IP' => '203.0.113.10',
                'Variables' => [
                    'Player Limit' => 64,
                    'bPassworded' => false,
                    'Mine Limit' => 12,
                ],
            ]],
            'directory_address' => '203.0.113.10',
        ]);

        self::assertTrue($result['gq_online']);
        self::assertSame('[EU] Test Server', $result['name']);
        self::assertSame(64, $result['player_limit']);
        self::assertSame(12, $result['variable_mine_limit']);
    }

    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testOpenRct2OfficialDirectory(): void
    {
        $result = $this->queryTest('127.0.0.1:11753', 'openrct2', [], false, [
            'directory_response' => ['servers' => [[
                'ip' => ['v4' => ['203.0.113.20'], 'v6' => ['2001:db8::20']],
                'port' => 11753,
                'version' => '0.5.1',
                'requiresPassword' => true,
                'players' => 4,
                'maxPlayers' => 16,
                'name' => 'OpenRCT2 Test',
                'description' => 'Building coasters',
                'gameInfo' => [
                    'mapSize' => ['x' => 148, 'y' => 148],
                    'day' => 30308,
                    'month' => 2264,
                    'guests' => 2234,
                    'parkValue' => 6958810,
                    'cash' => 123456,
                ],
            ]]],
            'directory_address' => '203.0.113.20',
        ]);

        self::assertTrue($result['gq_online']);
        self::assertSame('OpenRCT2 Test', $result['name']);
        self::assertSame(4, $result['players']);
        self::assertSame(16, $result['maxPlayers']);
        self::assertSame(148, $result['map_size_x']);
        self::assertSame(6958810, $result['park_value']);
    }
}
