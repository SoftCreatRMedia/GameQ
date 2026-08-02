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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace GameQ\Tests\Protocols;

/**
 * Test Class for Squad
 *
 * @package GameQ\Tests\Protocols
 */
class Squad extends Base
{
    /**
     * Test a Squad EOS matchmaking response
     */
    public function testResponse(): void
    {
        $testResult = $this->queryTest(
            '127.0.0.1:7787',
            'squad',
            [
                '{"access_token":"device-token"}',
                '{"access_token":"access-token"}',
                json_encode([
                    'sessions' => [
                        [
                            'attributes' => [
                                'ADDRESS_s' => '127.0.0.1',
                                'ADDRESSBOUND_s' => '0.0.0.0:7787',
                                'SERVERNAME_s' => 'Squad Test Server',
                                'MAPNAME_s' => 'Al Basrah',
                                'PASSWORD_b' => false,
                                'GAMEVERSION_s' => '8.2.1',
                            ],
                            'settings' => [
                                'maxPublicPlayers' => 100,
                            ],
                            'totalPlayers' => 42,
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            ],
            false,
            ['skip_http_requests' => true],
        );

        self::assertSame([
            'hostname' => 'Squad Test Server',
            'mapname' => 'Al Basrah',
            'password' => false,
            'version' => '8.2.1',
            'numplayers' => 42,
            'maxplayers' => 100,
            'gq_online' => true,
            'gq_address' => '127.0.0.1',
            'gq_port_client' => 7787,
            'gq_port_query' => 7787,
            'gq_protocol' => 'squad',
            'gq_type' => 'squad',
            'gq_name' => 'Squad',
            'gq_transport' => 'tcp',
            'gq_joinlink' => '',
        ], $testResult);
    }
}
