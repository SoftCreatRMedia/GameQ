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
class Satisfactory extends Base
{
    private const COOKIE = "\x01\x02\x03\x04\x05\x06\x07\x08";

    public function testLightweightServerStateResponse(): void
    {
        $serverName = 'FICSIT Test Server';
        $response = pack('vCC', 0xF6D5, 1, 1)
            . self::COOKIE
            . pack('CVPC', 3, 456789, 1, 2)
            . pack('Cv', 0, 12)
            . pack('Cv', 3, 4)
            . pack('v', strlen($serverName))
            . $serverName
            . "\x01";

        $result = $this->queryTest('127.0.0.1:7777', 'satisfactory', [$response]);

        self::assertTrue($result['gq_online']);
        self::assertSame($serverName, $result['server_name']);
        self::assertSame(3, $result['server_state']);
        self::assertSame('playing', $result['server_state_name']);
        self::assertSame(456789, $result['server_net_cl']);
        self::assertTrue($result['modded']);
        self::assertTrue($result['is_game_running']);
        self::assertSame(12, $result['sub_state_0']);
        self::assertSame(4, $result['sub_state_3']);
    }

    public function testInvalidTerminatorIsRejected(): void
    {
        $response = pack('vCC', 0xF6D5, 1, 1)
            . self::COOKIE
            . pack('CVPCvC', 1, 1, 0, 0, 0, 0);

        $this->expectException(\GameQ\Exception\ProtocolException::class);
        $this->expectExceptionMessageContains('invalid terminator');
        $this->queryTest('127.0.0.1:7777', 'satisfactory', [$response], true);
    }
}
