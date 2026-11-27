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
 */

namespace GameQ\Tests\Protocols;

use GameQ\Filters\Normalize;
use GameQ\Protocol;
use GameQ\Server;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Heroslegacy extends Base
{
    /**
     * @param list<string> $responses
     * @param non-empty-array<string, array<string, mixed>> $result
     */
    #[DataProvider('loadData')]
    public function testCapturedResponses(array $responses, array $result): void
    {
        $host = self::firstServerKey($result);
        $custom = $host !== '127.0.0.1:7777';
        $options = ['query_port' => $custom ? 18772 : 27016];
        $actual = $this->queryTest($host, 'heroslegacy', $responses, serverOptions: $options);
        self::assertSame($result[$host], $actual);
        $server = new Server(['type' => 'heroslegacy', 'host' => $host, 'options' => $options]);
        $normalized = (new Normalize())->apply($actual, $server);
        self::assertSame('GameQ Heros Legacy Local Test', $normalized['gq_hostname']);
        self::assertSame($custom ? 'GameQ Hardcore World' : 'Server World', $normalized['gq_mapname']);
        self::assertSame($custom ? 6 : 4, $normalized['gq_maxplayers']);
        self::assertSame($custom ? 0 : 1, $normalized['gq_password']);
        self::assertSame($custom ? 'v0.9.6,hc,d2' : 'v0.9.6,sc,d0', $normalized['keywords']);
        self::assertSame(0, $normalized['gq_numplayers']);
        self::assertSame('0.9.6', $normalized['version']);
        self::assertSame(5416430, $normalized['game_id']);
        self::assertSame('', $normalized['gq_joinlink']);

        $protocol = $server->protocolInstance();
        $protocol->beforeSend($server);
        $protocol->packetResponse([$responses[0]]);
        self::assertCount(1, $protocol->getFollowUpPackets());
        $protocol->packetResponse([$responses[1]]);
        self::assertSame([], $protocol->getFollowUpPackets());
    }

    public function testIndependentQueryPortAndUnsupportedRulesDefaults(): void
    {
        $server = new Server(['type' => 'heroslegacy', 'host' => '127.0.0.1:7777']);
        self::assertSame(27016, $server->portQuery());
        self::assertSame(Protocol::STATE_BETA, $server->protocolInstance()->state());
        self::assertNull($server->getJoinLink());
        $server = new Server(['type' => 'heroslegacy', 'host' => '127.0.0.1:17771']);
        self::assertSame(27016, $server->portQuery());
        $server = new Server([
            'type' => 'heroslegacy', 'host' => '127.0.0.1:17771', 'options' => ['query_port' => 18772],
        ]);
        self::assertSame(18772, $server->portQuery());
    }
}
