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
 * Counter-Strike 2 A2S protocol tests.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Cs2 extends Base
{
    public function testA2sInfoResponse(): void
    {
        $response = "\xFF\xFF\xFF\xFF\x49"
            . "\x11"
            . "Counter-Strike 2 Server\x00"
            . "de_dust2\x00"
            . "csgo\x00"
            . "Counter-Strike 2\x00"
            . pack('v', 730)
            . pack('CCC', 12, 64, 2)
            . "dl"
            . pack('CC', 0, 1)
            . "1.41.1.2\x00"
            . "\x91"
            . pack('v', 27015)
            . pack('P', 123456)
            . pack('P', 730);

        $protocol = new \GameQ\Protocols\Cs2();
        $protocol->packetResponse([$response]);

        self::assertSame([
            'protocol' => 17,
            'hostname' => 'Counter-Strike 2 Server',
            'map' => 'de_dust2',
            'game_dir' => 'csgo',
            'game_descr' => 'Counter-Strike 2',
            'steamappid' => 730,
            'num_players' => 12,
            'max_players' => 64,
            'num_bots' => 2,
            'dedicated' => 'd',
            'os' => 'l',
            'password' => 0,
            'secure' => 1,
            'version' => '1.41.1.2',
            'port' => 27015,
            'steam_id' => 123456,
            'game_id' => 730,
        ], $protocol->processResponse());
    }
}
