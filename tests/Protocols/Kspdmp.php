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
class Kspdmp extends Base
{
    /**
     * @throws ReflectionException
     * @throws JsonException
     * @throws ServerException
     */
    public function testOfficialHttpStatusDocument(): void
    {
        $response = json_encode([
            'server_name' => 'Kerbin',
            'version' => '0.4.1',
            'protocol_version' => 49,
            'players' => 'Alice, Bob',
            'player_count' => 2,
            'max_players' => 20,
            'port' => 6702,
            'game_mode' => 'SANDBOX',
            'warp_mode' => 'MCW_FORCE',
            'mod_control' => 2,
            'cheats' => false,
        ], JSON_THROW_ON_ERROR);

        $result = $this->queryTest('127.0.0.1:6702', 'kspdmp', [$response]);

        self::assertTrue($result['gq_online']);
        self::assertSame('Kerbin', $result['server_name']);
        self::assertSame(2, $result['player_count']);
        self::assertIsArray($result['players']);
        self::assertIsArray($result['players'][0]);
        self::assertIsArray($result['players'][1]);
        self::assertSame('Alice', $result['players'][0]['name']);
        self::assertSame('Bob', $result['players'][1]['name']);
        self::assertSame(6703, $result['gq_port_query']);
    }
}
