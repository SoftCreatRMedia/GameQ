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

use GameQ\Exception\ServerException;
use GameQ\Protocols\Uptime as UptimeProtocol;
use GameQ\Protocol;
use GameQ\Protocols\Source;
use GameQ\Server;
use GameQ\Tests\Fixtures\HttpClient;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionException;

/**
 * Tests for the Uptime: A Cloud Provider Sim A2S protocol.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Uptime extends Base
{
    /**
     * @param list<string> $responses
     * @param non-empty-array<string, array<string, mixed>> $result
     *
     * @throws ReflectionException
     * @throws ServerException
     */
    #[DataProvider('loadData')]
    public function testResponses(array $responses, array $result): void
    {
        $server = self::firstServerKey($result);
        $expected = $result[$server];
        $actual = $this->queryTest(
            $server,
            'uptime',
            $responses,
            serverOptions: [Server::SERVER_OPTIONS_QUERY_PORT => $expected['gq_port_query']],
        );

        ksort($expected);
        ksort($actual);

        self::assertSame($expected, $actual);
    }

    /**
     * @throws ServerException
     */
    public function testProtocolMetadataAndPorts(): void
    {
        $server = new Server([
            Server::SERVER_TYPE => 'uptime',
            Server::SERVER_HOST => '192.0.2.10:27016',
        ]);
        $protocol = $server->protocolInstance();

        self::assertInstanceOf(Source::class, $protocol);
        self::assertSame('uptime', (string) $protocol);
        self::assertSame('Uptime: A Cloud Provider Sim', $protocol->nameLong());
        self::assertSame(Protocol::STATE_STABLE, $protocol->state());
        self::assertSame(0, $protocol->portDiff());
        self::assertSame(27016, $server->portQuery());
        self::assertSame(Protocol::TRANSPORT_UDP, $protocol->transport());
        self::assertNull($protocol->joinLink());
        self::assertNull($server->getJoinLink());

        $overriddenServer = new Server([
            Server::SERVER_TYPE => 'uptime',
            Server::SERVER_HOST => '192.0.2.10:27016',
            Server::SERVER_OPTIONS => [Server::SERVER_OPTIONS_QUERY_PORT => 27123],
        ]);

        self::assertSame(27123, $overriddenServer->portQuery());
    }

    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testMissingResponseIsOffline(): void
    {
        self::assertFalse($this->queryTest('192.0.2.10:27016', 'uptime', [])['gq_online']);
    }

    /**
     * The official loopback monitoring API is an oracle for fixture collection,
     * but it is never a production transport for GameQ.
     *
     * @throws ServerException
     */
    public function testProcessingCapturedPacketsDoesNotUseHttp(): void
    {
        $server = new Server([
            Server::SERVER_TYPE => 'uptime',
            Server::SERVER_HOST => '192.0.2.10:27016',
        ]);
        $client = new HttpClient([]);
        $protocol = $server->protocolInstance()->setHttpClient($client);
        $responses = explode(
            PHP_EOL . '||' . PHP_EOL,
            self::fixtureContents(__DIR__ . '/Providers/Uptime/1_response.txt'),
        );

        $protocol->beforeSend($server);
        $protocol->packetResponse($responses);
        self::assertSame('GameQ Fixture Empty', $protocol->processResponse()['hostname']);
        self::assertSame([], $client->requests);
    }

    /**
     * @throws ServerException
     */
    public function testUnsupportedRulesQueryIsDisabledByDefault(): void
    {
        $server = new Server([
            Server::SERVER_TYPE => 'uptime',
            Server::SERVER_HOST => '192.0.2.10:27016',
        ]);
        $protocol = $server->protocolInstance();
        $protocol->beforeSend($server);
        $protocol->packetResponse(["\xFF\xFF\xFF\xFF\x49details"]);
        self::assertSame(
            ["\xFF\xFF\xFF\xFF\x55\xFF\xFF\xFF\xFF"],
            $protocol->getFollowUpPackets(),
        );
        $protocol->appendPacketResponse(["\xFF\xFF\xFF\xFF\x44\x00"]);
        self::assertSame([], $protocol->getFollowUpPackets());

        $rulesProtocol = new UptimeProtocol(['query_rules' => true]);
        $rulesProtocol->beforeSend($server);
        $rulesProtocol->packetResponse(["\xFF\xFF\xFF\xFF\x49details"]);
        $rulesProtocol->getFollowUpPackets();
        $rulesProtocol->appendPacketResponse(["\xFF\xFF\xFF\xFF\x44\x00"]);
        self::assertSame(
            ["\xFF\xFF\xFF\xFF\x56\xFF\xFF\xFF\xFF"],
            $rulesProtocol->getFollowUpPackets(),
        );
    }
}
