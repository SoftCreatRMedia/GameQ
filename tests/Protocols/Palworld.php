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

/**
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Palworld extends Base
{
    public function testRestResponsesAreParsedWithoutNetworkAccess(): void
    {
        $gameQ = new \GameQ\GameQ();
        $gameQ->addServer([
            'id' => 'palworld-test',
            'type' => 'palworld',
            'host' => '127.0.0.1:8211',
            'options' => [
                'api_responses' => [
                    'info' => [
                        'version' => 'v1.0.0',
                        'servername' => 'Palworld Test',
                        'description' => 'A test server',
                        'worldguid' => 'A7E97BAA767DB9029EF013BB71E993A0',
                    ],
                    'players' => [
                        'players' => [
                            [
                                'name' => 'PalUser',
                                'accountName' => 'paluser',
                                'playerId' => 'AFAFD830000000000000000000000000',
                                'userId' => 'steam_00000000000000000',
                                'ip' => '127.0.0.1',
                                'ping' => 3.14,
                                'level' => 12,
                            ],
                        ],
                    ],
                    'metrics' => [
                        'serverfps' => 57,
                        'currentplayernum' => 1,
                        'maxplayernum' => 32,
                        'uptime' => 3600,
                        'days' => 5,
                    ],
                    'settings' => [
                        'Difficulty' => 'Normal',
                        'ServerPlayerMaxNum' => 32,
                        'RESTAPIEnabled' => true,
                        'RESTAPIPort' => 8212,
                    ],
                ],
            ],
        ]);

        $result = $gameQ->process()['palworld-test'];

        self::assertTrue($result['gq_online']);
        self::assertSame('Palworld Test', $result['gq_hostname']);
        self::assertSame(32, $result['gq_maxplayers']);
        self::assertSame(1, $result['gq_numplayers']);
        self::assertSame(8212, $result['gq_port_query']);
        self::assertSame('v1.0.0', $result['version']);
        $players = $result['players'];
        self::assertIsArray($players);
        $player = $players[0] ?? null;
        self::assertIsArray($player);
        self::assertSame('PalUser', $player['gq_name']);
        self::assertSame(3.14, $player['gq_ping']);
        self::assertSame(12, $player['level']);
    }

    public function testMissingRestResponseIsOffline(): void
    {
        $result = $this->queryTest(
            '127.0.0.1:8211',
            'palworld',
            [],
            false,
            ['api_responses' => []],
        );

        self::assertFalse($result['gq_online']);
    }
}
