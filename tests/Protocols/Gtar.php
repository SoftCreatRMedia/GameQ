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

class Gtar extends Base
{
    /**
     * Holds stub on setup
     *
     * @var \GameQ\Protocols\Gtar
     */
    protected \GameQ\Protocols\Gtar $stub;

    /**
     * Holds the expected packets for this protocol class
     *
     * @var array<string, string>
     */
    protected array $packets = [
        \GameQ\Protocol::PACKET_STATUS => "GET /master/ HTTP/1.0\r\nHost: cdn.rage.mp\r\nAccept: */*\r\n\r\n",
    ];

    /**
     * Setup
     *
     */
    #[\PHPUnit\Framework\Attributes\Before]
    public function customSetUp(): void
    {
        // Create the stub class
        $this->stub = new \GameQ\Protocols\Gtar();
    }

    /**
     * Test the packets to make sure they are correct for source
     */
    public function testPackets(): void
    {
        // Test to make sure packets are defined properly
        self::assertEquals($this->packets, $this->stub->getPacket());
    }

    /**
     * Test responses for Grand Theft Auto Network
     *
     *
     * @param list<string> $responses
     * @param non-empty-array<string, array<string, mixed>> $result
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('loadData')]
    public function testResponses(array $responses, array $result): void
    {
        // Pull the first key off the array this is the server ip:port
        $server = self::firstServerKey($result);

        $testResult = $this->queryTest(
            $server,
            'gtar',
            $responses,
        );
        $result[$server]['gq_joinlink'] = 'rage://v/connect/' . $server;

        foreach ($result[$server] as $key => $value) {
            self::assertArrayHasKey($key, $testResult);
            self::assertEqualsDelta($value, $testResult[$key], 0.000000001);
        }
    }

    public function testExtendedMasterListDataIsExposed(): void
    {
        $result = $this->queryTest(
            '203.0.113.5:22005',
            'gtar',
            [
                "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n"
                . "Nel: {\"report_to\":\"cf-nel\"}\r\n\r\n"
                . '{"203.0.113.5:22005":{"name":"Rage Test","gamemode":"Roleplay",'
                . '"url":"https://example.com","lang":"en","players":12,"peak":24,"maxplayers":128}}',
            ],
        );

        self::assertSame('rage://v/connect/203.0.113.5:22005', $result['gq_joinlink']);
        self::assertSame('https://example.com', $result['ragemp_website']);
        self::assertSame('en', $result['ragemp_primary_language']);
        self::assertSame(24, $result['ragemp_player_peak']);
    }
}
