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

use GameQ\GameQ;
use GameQ\Server;

/**
 * SCUM master-server protocol tests.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Scum extends Base
{
    public function testCurrentMasterResponseIsProcessedWithoutNetworkQuery(): void
    {
        $gameQ = new GameQ();
        $gameQ->addServer([
            Server::SERVER_ID => 'scum-test',
            Server::SERVER_TYPE => 'scum',
            Server::SERVER_HOST => '203.0.113.5:7777',
            Server::SERVER_OPTIONS => [
                'master_response' => "\x01\x00\x00\x00" . $this->createServerRecord(),
            ],
        ]);

        $result = $gameQ->process()['scum-test'];

        self::assertTrue($result['gq_online']);
        self::assertSame('203.0.113.5', $result['gq_address']);
        self::assertSame(7777, $result['gq_port_client']);
        self::assertSame(7777, $result['gq_port_query']);
        self::assertSame('SCUM Test Server', $result['gq_hostname']);
        self::assertSame(64, $result['gq_maxplayers']);
        self::assertSame(7, $result['gq_numplayers']);
        self::assertSame('13:30', $result['time']);
        self::assertSame('1.2.3.4', $result['version']);
        self::assertSame('steam://connect/203.0.113.5:7777/', $result['gq_joinlink']);
    }

    public function testMissingMasterServerIsOffline(): void
    {
        $result = $this->queryTest(
            '203.0.113.5:7777',
            'scum',
            [],
            false,
            ['master_response' => "\x00\x00\x00\x00"],
        );

        self::assertFalse($result['gq_online']);
    }

    private function createServerRecord(): string
    {
        $name = 'SCUM Test Server';

        return pack('C4v', 5, 113, 0, 203, 7777)
            . "\x00\x00\x00"
            . pack('CC', 7, 64)
            . "\x00\x00"
            . pack('g', 13.5)
            . "\x00\x00\x00\x00"
            . pack('VvCC', 4, 3, 2, 1)
            . pack('C', strlen($name))
            . $name;
    }
}
