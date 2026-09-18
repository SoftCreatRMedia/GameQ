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

use GameQ\Exception\ProtocolException;
use GameQ\Exception\ServerException;
use GameQ\Protocol;
use GameQ\Protocols\Metin2 as Metin2Protocol;
use GameQ\Server;
use ReflectionException;

/**
 * Metin2 protocol tests.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Metin2 extends Base
{
    /**
     * @throws ReflectionException
     * @throws ProtocolException
     * @throws ServerException
     */
    public function testCanonicalChannelStatusResponse(): void
    {
        $server = new Server([
            Server::SERVER_TYPE => 'metin2',
            Server::SERVER_HOST => '127.0.0.1:13000',
        ]);
        $protocol = $server->protocolInstance();

        self::assertSame(Protocol::TRANSPORT_TCP, $protocol->transport());
        self::assertSame(Protocol::STATE_BETA, $protocol->state());
        self::assertSame("\xCE", $protocol->getPacket(Protocol::PACKET_STATUS));

        $response = "\xFD\x01"
            . "\xD2"
            . pack('V', 3)
            . pack('vC', 13000, 1)
            . pack('vC', 13010, 2)
            . pack('vC', 13020, 3)
            . "\x01";
        $result = $this->queryTest('127.0.0.1:13000', 'metin2', [$response]);

        self::assertTrue($result['gq_online']);
        self::assertSame('channel_status', $result['query_mode']);
        self::assertSame('standard', $result['status_format']);
        self::assertSame(3, $result['channel_count']);
        self::assertSame([
            ['port' => 13000, 'status' => 1, 'status_name' => 'normal'],
            ['port' => 13010, 'status' => 2, 'status_name' => 'busy'],
            ['port' => 13020, 'status' => 3, 'status_name' => 'full'],
        ], $result['channels']);
        self::assertSame(1, $result['status']);
        self::assertSame('normal', $result['status_name']);
        self::assertTrue($result['accepting_players']);
        self::assertArrayNotHasKey('num_players', $result);
    }

    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testLiveCanonicalChannelStatusResponse(): void
    {
        // Captured from open-mt2 while a player was actively connected.
        $response = hex2bin('fd01ff9b351245ce47140000000000d201000000c9320101');

        self::assertIsString($response);

        $result = $this->queryTest('127.0.0.1:13001', 'metin2', [$response]);

        self::assertTrue($result['gq_online']);
        self::assertSame('channel_status', $result['query_mode']);
        self::assertSame('standard', $result['status_format']);
        self::assertSame([
            ['port' => 13001, 'status' => 1, 'status_name' => 'normal'],
        ], $result['channels']);
        self::assertSame('normal', $result['status_name']);
        self::assertTrue($result['accepting_players']);
        self::assertArrayNotHasKey('num_players', $result);
    }

    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testKnownExtendedChannelStatusResponse(): void
    {
        $response = "\xD2"
            . pack('V', 2)
            . pack('vCV', 13000, 1, 17)
            . pack('vCV', 13010, 0, 0)
            . "\x01";
        $result = $this->queryTest('127.0.0.1:13010', 'metin2', [$response]);

        self::assertTrue($result['gq_online']);
        self::assertSame('extended', $result['status_format']);
        self::assertSame([
            ['port' => 13000, 'status' => 1, 'status_name' => 'normal', 'players' => 17],
            ['port' => 13010, 'status' => 0, 'status_name' => 'closed', 'players' => 0],
        ], $result['channels']);
        self::assertSame(0, $result['status']);
        self::assertSame('closed', $result['status_name']);
        self::assertFalse($result['accepting_players']);
        self::assertSame(0, $result['num_players']);
    }

    /**
     * @throws ReflectionException
     * @throws ServerException
     * @throws ProtocolException
     */
    public function testAdminPageUserCountQuery(): void
    {
        $server = new Server([
            Server::SERVER_TYPE => 'metin2',
            Server::SERVER_HOST => '127.0.0.1:13000',
            Server::SERVER_OPTIONS => [
                'admin_page_query' => true,
            ],
        ]);
        $protocol = $server->protocolInstance();
        $protocol->beforeSend($server);

        self::assertSame("\x40IS_SERVER_UP\x0A", $protocol->getPacket(Protocol::PACKET_STATUS));
        self::assertSame("\x40USER_COUNT\x0A", $protocol->getPacket(Protocol::PACKET_DETAILS));

        $result = $this->queryTest(
            '127.0.0.1:13000',
            'metin2',
            ["\xFD\x01\xFF\x00" . "YES\x0A" . '99 30 25 44 12' . "\x0A"],
            false,
            ['admin_page_query' => 1],
        );

        self::assertTrue($result['gq_online']);
        self::assertSame('admin_page', $result['query_mode']);
        self::assertSame(1, $result['status']);
        self::assertSame('normal', $result['status_name']);
        self::assertTrue($result['accepting_players']);
        self::assertSame(12, $result['num_players']);
        self::assertSame(99, $result['num_players_total']);
        self::assertSame(30, $result['players_shinsoo']);
        self::assertSame(25, $result['players_chunjo']);
        self::assertSame(44, $result['players_jinno']);
        self::assertSame(12, $result['players_local']);
    }

    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testLiveAdminPageUserCountResponse(): void
    {
        // Captured from the combined query with no players connected.
        $response = hex2bin('fd01ffe1f7793cf1410000000000005945530a3020302030203020300a');

        self::assertIsString($response);

        $result = $this->queryTest(
            '127.0.0.1:13001',
            'metin2',
            [$response],
            false,
            ['admin_page_query' => true],
        );

        self::assertTrue($result['gq_online']);
        self::assertSame('admin_page', $result['query_mode']);
        self::assertTrue($result['accepting_players']);
        self::assertSame(0, $result['num_players']);
        self::assertSame(0, $result['num_players_total']);
        self::assertSame(0, $result['players_shinsoo']);
        self::assertSame(0, $result['players_chunjo']);
        self::assertSame(0, $result['players_jinno']);
        self::assertSame(0, $result['players_local']);
    }

    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testLiveMixedEmpireUserCountResponse(): void
    {
        // USER_COUNT captured with one Shinsoo and two Jinno players; prepend the availability response.
        $userCountResponse = hex2bin('fd01ffc88f83b410ef0300000000003320312030203220330a');

        self::assertIsString($userCountResponse);

        $result = $this->queryTest(
            '127.0.0.1:13001',
            'metin2',
            ["YES\x0A" . substr($userCountResponse, -10)],
            false,
            ['admin_page_query' => true],
        );

        self::assertSame(3, $result['num_players']);
        self::assertSame(3, $result['num_players_total']);
        self::assertSame(1, $result['players_shinsoo']);
        self::assertSame(0, $result['players_chunjo']);
        self::assertSame(2, $result['players_jinno']);
        self::assertSame(3, $result['players_local']);
    }

    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testZeroChannelResponse(): void
    {
        $response = "\xD2" . pack('V', 0) . "\x01";
        $result = $this->queryTest('127.0.0.1:13000', 'metin2', [$response]);

        self::assertTrue($result['gq_online']);
        self::assertSame(0, $result['channel_count']);
        self::assertSame([], $result['channels']);
        self::assertArrayNotHasKey('status', $result);
    }

    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testMalformedChannelStatusIsRejected(): void
    {
        $response = "\xD2"
            . pack('V', 1)
            . pack('vC', 13000, 4)
            . "\x01";

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains('invalid or ambiguous channel-status response');
        $this->queryTest('127.0.0.1:13000', 'metin2', [$response], true);
    }

    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testTruncatedExtendedChannelStatusIsRejected(): void
    {
        $response = "\xD2"
            . pack('V', 1)
            . pack('vC', 13000, 1)
            . pack('V', 12);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains('invalid or ambiguous channel-status response');
        $this->queryTest('127.0.0.1:13000', 'metin2', [$response], true);
    }

    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testRejectedAdminPageQueryIsRejected(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains('ADMINPAGE_IP');
        $this->queryTest(
            '127.0.0.1:13000',
            'metin2',
            ['YES' . "\x0A" . 'WEBADMIN : Wrong Connector : 127.0.0.1' . "\x0A"],
            true,
            ['admin_page_query' => true],
        );
    }

    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testAdminPageReportsClosedServer(): void
    {
        $result = $this->queryTest(
            '127.0.0.1:13000',
            'metin2',
            ["NO\x0A0 0 0 0 0\x0A"],
            false,
            ['admin_page_query' => true],
        );

        self::assertTrue($result['gq_online']);
        self::assertSame(0, $result['status']);
        self::assertSame('closed', $result['status_name']);
        self::assertFalse($result['accepting_players']);
        self::assertSame(0, $result['num_players']);
    }

    /**
     * @throws ServerException
     */
    public function testInvalidAdminPageOptionIsRejected(): void
    {
        $protocol = new Metin2Protocol(['admin_page_query' => 'yes']);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains("'admin_page_query' option must be a boolean");
        $protocol->beforeSend(new Server([
            Server::SERVER_TYPE => 'metin2',
            Server::SERVER_HOST => '127.0.0.1:13000',
        ]));
    }
}
