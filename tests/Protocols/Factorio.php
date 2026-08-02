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
class Factorio extends Base
{
    public function testMatchmakingResponseIsParsedWithoutNetworkAccess(): void
    {
        $gameQ = new \GameQ\GameQ();
        $gameQ->addServer([
            'id' => 'factorio-test',
            'type' => 'factorio',
            'host' => '127.0.0.1:34197',
            'options' => [
                'matchmaking_response' => [
                    'application_version' => [
                        'build_mode' => 'headless',
                        'build_version' => '81234',
                        'game_version' => '2.0.60',
                        'platform' => 'linux64',
                    ],
                    'description' => 'Factory test server',
                    'game_time_elapsed' => '12345',
                    'has_password' => 'true',
                    'host_address' => '127.0.0.1:34197',
                    'max_players' => '20',
                    'name' => 'Factorio Test',
                    'players' => ['Alice', 'Bob'],
                    'tags' => ['game', 'test'],
                ],
            ],
        ]);

        $result = $gameQ->process()['factorio-test'];

        self::assertSame('Factorio Test', $result['gq_hostname']);
        self::assertSame('20', $result['gq_maxplayers']);
        self::assertSame(2, $result['gq_numplayers']);
        self::assertSame('true', $result['gq_password']);
        self::assertSame('2.0.60', $result['game_version']);
        self::assertSame([
            ['name' => 'Alice', 'gq_name' => 'Alice'],
            ['name' => 'Bob', 'gq_name' => 'Bob'],
        ], $result['players']);
    }

    public function testMissingMatchmakingServerIsOffline(): void
    {
        $result = $this->queryTest(
            '127.0.0.1:34197',
            'factorio',
            [],
            false,
            ['matchmaking_response' => []],
        );

        self::assertFalse($result['gq_online']);
    }
}
