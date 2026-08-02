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
use GameQ\Protocol;
use GameQ\Protocols\Fs13;
use GameQ\Protocols\Fs25;
use GameQ\Tests\TestBase;

/**
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Farmingsimulator extends TestBase
{
    public function testJsonResponseIsParsed(): void
    {
        $response = json_encode([
            'server' => [
                'dayTime' => 42_000,
                'game' => 'Farming Simulator 25',
                'mapName' => 'Riverbend Springs',
                'mapSize' => 2_048,
                'mapOverviewFilename' => 'maps/mapUS/mapOverview.png',
                'money' => 0,
                'name' => 'Example Farm',
                'version' => '1.21.0.0',
            ],
            'slots' => [
                'capacity' => 16,
                'used' => 2,
                'players' => [
                    [
                        'isUsed' => true,
                        'isAdmin' => true,
                        'uptime' => 120,
                        'name' => 'Alice',
                    ],
                    [
                        'isUsed' => false,
                        'name' => '',
                    ],
                    [
                        'isUsed' => true,
                        'isAdmin' => false,
                        'name' => 'Bob',
                    ],
                ],
            ],
            'vehicles' => [
                ['name' => 'Tractor', 'category' => 'tractors'],
            ],
            'mods' => [
                [
                    'author' => 'GIANTS Software',
                    'name' => 'FS25_precisionFarming',
                    'version' => '1.0.0.0',
                    'description' => 'Precision Farming',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $this->queryTest(
            '127.0.0.1:10823',
            'fs25',
            ["HTTP/1.0 200 OK\r\nContent-Type: application/json\r\n\r\n" . $response],
            true,
            [
                'web_code' => 'secret',
                'web_port' => 8080,
            ],
        );

        self::assertTrue($result['gq_online']);
        self::assertSame(8080, $result['gq_port_query']);
        self::assertSame('fs25', $result['gq_type']);
        self::assertSame('farmingsimulator', $result['gq_protocol']);
        self::assertSame('Example Farm', $result['hostname']);
        self::assertSame('Riverbend Springs', $result['mapname']);
        self::assertSame(16, $result['maxplayers']);
        self::assertSame(2, $result['numplayers']);
        self::assertSame('1.21.0.0', $result['version']);
        self::assertSame([
            [
                'isUsed' => true,
                'isAdmin' => true,
                'uptime' => 120,
                'name' => 'Alice',
            ],
            [
                'isUsed' => true,
                'isAdmin' => false,
                'name' => 'Bob',
            ],
        ], $result['players']);
        $mods = $result['mods'];
        self::assertIsArray($mods);
        self::assertArrayHasKey(0, $mods);
        self::assertIsArray($mods[0]);
        self::assertSame('Precision Farming', $mods[0]['description']);
    }

    public function testXmlResponseIsParsed(): void
    {
        $response = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <Server game="Farming Simulator 2013" version="2.1.0.2" server="International"
                    name="Old Farm" mapName="Hagenstedt" money="5993" dayTime="24687375"
                    mapOverviewFilename="data/maps/map01/pda_map.png">
                <Slots capacity="16" numUsed="2">
                    <Player isUsed="true">Alice</Player>
                    <Player isUsed="false" />
                    <Player isUsed="true">Bob</Player>
                </Slots>
                <Vehicles>
                    <Vehicle name="Tractor" />
                </Vehicles>
            </Server>
            XML;

        $result = $this->queryTest(
            '127.0.0.1:10823',
            'fs13',
            ["HTTP/1.0 200 OK\r\nContent-Type: application/xml\r\n\r\n" . $response],
            true,
            [
                'web_code' => 'secret',
                'web_port' => 8080,
            ],
        );

        self::assertTrue($result['gq_online']);
        self::assertSame('Old Farm', $result['hostname']);
        self::assertSame('Hagenstedt', $result['mapname']);
        self::assertSame(16, $result['maxplayers']);
        self::assertSame(2, $result['numplayers']);
        self::assertSame([['name' => 'Alice'], ['name' => 'Bob']], $result['players']);
        $slots = $result['Slots'];
        self::assertIsArray($slots);
        self::assertSame('16', $slots['capacity']);
        $vehicles = $result['Vehicles'];
        self::assertIsArray($vehicles);
        $vehicle = $vehicles['Vehicle'];
        self::assertIsArray($vehicle);
        self::assertSame('Tractor', $vehicle['name']);
        self::assertSame('International', $result['fs_server_region']);
    }

    public function testPacketsUseCorrectFormatAndEncodedCode(): void
    {
        $fs13 = new Fs13(['web_code' => 'a b&c']);
        $fs25 = new Fs25(['web_code' => 'a b&c']);
        $fs13Packet = $fs13->getPacket(Protocol::PACKET_STATUS);
        $fs25Packet = $fs25->getPacket(Protocol::PACKET_STATUS);
        self::assertIsString($fs13Packet);
        self::assertIsString($fs25Packet);

        self::assertStringContainsString(
            'GET /feed/dedicated-server-stats.xml?code=a%20b%26c HTTP/1.0',
            $fs13Packet,
        );
        self::assertStringContainsString('Accept: application/xml', $fs13Packet);
        self::assertStringContainsString(
            'GET /feed/dedicated-server-stats.json?code=a%20b%26c HTTP/1.0',
            $fs25Packet,
        );
        self::assertStringContainsString('Accept: application/json', $fs25Packet);
    }

    public function testZeroCapacityMeansOffline(): void
    {
        $protocol = new Fs25();
        $protocol->packetResponse([
            '{"server":{"name":"Stopped"},"slots":{"capacity":0,"used":0,"players":[]}}',
        ]);

        self::assertSame([], $protocol->processResponse());
    }

    public function testHttpErrorIsRejected(): void
    {
        $protocol = new Fs25();
        $protocol->packetResponse(["HTTP/1.0 403 Forbidden\r\nContent-Length: 0\r\n\r\n"]);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains('Farming Simulator returned HTTP status 403.');

        $protocol->processResponse();
    }
}
