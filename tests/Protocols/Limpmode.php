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
class Limpmode extends Base
{
    /**
     * @param list<string> $responses
     * @param non-empty-array<string, array<string, mixed>> $result
     */
    #[DataProvider('loadData')]
    public function testCapturedResponses(array $responses, array $result): void
    {
        $host = self::firstServerKey($result);
        $actual = $this->queryTest($host, 'limpmode', $responses);
        self::assertSame($result[$host], $actual);
        $server = new Server(['type' => 'limpmode', 'host' => $host]);
        $normalized = (new Normalize())->apply($actual, $server);
        self::assertTrue($normalized['gq_online']);
        self::assertSame('GameQ LIMP MODE Local Test', $normalized['gq_hostname']);
        self::assertSame(0, $normalized['gq_numplayers']);
        self::assertSame(4, $normalized['gq_maxplayers']);
        self::assertSame('0.68', $normalized['version']);
        self::assertSame(5024540, $normalized['game_id']);
        self::assertSame($actual['map'], $normalized['gq_gametype']);
        self::assertArrayNotHasKey('gq_mapname', $normalized);
        self::assertSame('', $normalized['gq_joinlink']);
    }

    public function testQuerySocketIsNotTheGameplayOrRelayEndpoint(): void
    {
        $server = new Server(['type' => 'limpmode', 'host' => '127.0.0.1:27016']);
        self::assertSame(27016, $server->portQuery());
        self::assertNull($server->getJoinLink());
        self::assertSame(Protocol::STATE_BETA, $server->protocolInstance()->state());
        $server = new Server(['type' => 'limpmode', 'host' => '127.0.0.1:27018']);
        self::assertSame(27018, $server->portQuery());
        $server = new Server([
            'type' => 'limpmode', 'host' => '127.0.0.1:27016', 'options' => ['query_port' => 27018],
        ]);
        self::assertSame(27018, $server->portQuery());
    }

    public function testUnansweredQueriesStayOffline(): void
    {
        self::assertFalse($this->queryTest('127.0.0.1:27016', 'limpmode', [])['gq_online']);
    }
}
