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

class Beammp extends Base
{
    public function testBackendServerIsParsedWithoutNetworkAccess(): void
    {
        $gameQ = new \GameQ\GameQ();
        $gameQ->addServer([
            'id' => 'beammp-test',
            'type' => 'beammp',
            'host' => '127.0.0.1:30814',
            'options' => [
                'backend_response' => [
                    [
                        'ip' => '127.0.0.1',
                        'port' => '30814',
                        'sname' => 'BeamMP Test',
                        'players' => '2',
                        'playerslist' => 'Alice;Bob;',
                        'maxplayers' => '8',
                        'map' => '/levels/gridmap_v2/info.json',
                        'password' => false,
                        'version' => '3.9.3',
                    ],
                ],
            ],
        ]);

        $result = $gameQ->process()['beammp-test'];

        self::assertTrue($result['gq_online']);
        self::assertSame('BeamMP Test', $result['gq_hostname']);
        self::assertIsNumeric($result['gq_maxplayers']);
        self::assertSame(8, (int) $result['gq_maxplayers']);
        self::assertSame(2, $result['gq_numplayers']);
        self::assertSame(2, $result['players_count']);
        self::assertSame([
            ['name' => 'Alice', 'gq_name' => 'Alice'],
            ['name' => 'Bob', 'gq_name' => 'Bob'],
        ], $result['players']);
    }

    public function testMissingBackendServerIsOffline(): void
    {
        $result = $this->queryTest(
            '127.0.0.1:30814',
            'beammp',
            [],
            false,
            ['backend_response' => []],
        );

        self::assertFalse($result['gq_online']);
    }
}
