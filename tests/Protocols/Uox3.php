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
class Uox3 extends Base
{
    public function testUoGatewayStatusResponse(): void
    {
        $response = "UOX3, Britannia Test, Age=12, Clients=7, Items=1234, Chars=567, Mem=890K\x00";
        $server = new \GameQ\Server([
            \GameQ\Server::SERVER_TYPE => 'uox3',
            \GameQ\Server::SERVER_HOST => '127.0.0.1:2593',
        ]);

        self::assertSame(
            "\x7F\x00\x00\x01\xF1\x00\x04\xFF",
            $server->protocolInstance()->getPacket(\GameQ\Protocol::PACKET_STATUS),
        );

        $result = $this->queryTest('127.0.0.1:2593', 'uox3', [$response]);

        self::assertTrue($result['gq_online']);
        self::assertSame('UOX3', $result['engine']);
        self::assertSame('Britannia Test', $result['hostname']);
        self::assertSame(12, $result['age_hours']);
        self::assertSame(43_200, $result['uptime']);
        self::assertSame(7, $result['num_players']);
        self::assertSame(1234, $result['items']);
        self::assertSame(567, $result['characters']);
        self::assertSame(890, $result['memory_kb']);
    }

    public function testMalformedResponseIsRejected(): void
    {
        $this->expectException(\GameQ\Exception\ProtocolException::class);
        $this->expectExceptionMessageContains('invalid server poll response');
        $this->queryTest('127.0.0.1:2593', 'uox3', ['invalid'], true);
    }
}
