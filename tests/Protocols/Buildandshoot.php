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
use JsonException;
use ReflectionException;

/**
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Buildandshoot extends Base
{
    /**
     * @throws ReflectionException
     * @throws JsonException
     * @throws ServerException
     */
    public function testOfficialStatusDocument(): void
    {
        $response = json_encode([
            'serverName' => 'Ace Server',
            'serverVersion' => '1.3.0',
            'gameMode' => 'ctf',
            'map' => ['name' => 'Hallway', 'author' => 'test'],
            'players' => [
                'blue' => [['name' => 'Alice', 'latency' => 33, 'kills' => 5, 'team' => 'Blue']],
                'green' => [['name' => 'Bob', 'latency' => 55, 'kills' => 2, 'team' => 'Green']],
            ],
            'maxPlayers' => 32,
        ], JSON_THROW_ON_ERROR);

        $result = $this->queryTest('127.0.0.1:32887', 'buildandshoot', [$response]);

        self::assertTrue($result['gq_online']);
        self::assertSame('Ace Server', $result['server_name']);
        self::assertSame('Hallway', $result['map_name']);
        self::assertSame(2, $result['num_players']);
        self::assertSame(32, $result['max_players']);
        self::assertIsArray($result['players']);
        self::assertIsArray($result['players'][0]);
        self::assertIsArray($result['players'][1]);
        self::assertSame('Alice', $result['players'][0]['name']);
        self::assertSame(55, $result['players'][1]['latency']);
        self::assertSame(32886, $result['gq_port_query']);
    }
}
