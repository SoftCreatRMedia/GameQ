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
 * Test Class for TShock
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Tshock extends Base
{
    /**
     * Test responses that omit the optional player and rules lists
     */
    public function testResponseWithoutPlayersOrRules(): void
    {
        $protocol = new \GameQ\Protocols\Tshock();
        $protocol->packetResponse([
            "HTTP/1.0 200 OK\r\n\r\n" . json_encode([
                'status' => 200,
                'name' => 'Penguin Games',
                'serverversion' => '1.4.4.9 (PC/Mobile)',
                'tshockversion' => '4.4.0.15',
                'port' => 7777,
                'playercount' => 54,
                'maxplayers' => 99999,
                'world' => 'Penguin Games',
                'uptime' => '6.14:41:30',
                'serverpassword' => false,
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame([
            'dedicated' => 1,
            'hostname' => 'Penguin Games',
            'game_port' => 7777,
            'serverversion' => '1.4.4.9 (PC/Mobile)',
            'world' => 'Penguin Games',
            'uptime' => '6.14:41:30',
            'password' => 0,
            'numplayers' => 54,
            'maxplayers' => 99999,
            'rules' => [],
        ], $protocol->processResponse());
    }
}
