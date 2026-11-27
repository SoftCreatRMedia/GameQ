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
class Ruinstofortress extends Base
{
    /**
     * @param list<string> $responses
     * @param non-empty-array<string, array<string, mixed>> $result
     */
    #[DataProvider('loadData')]
    public function testCapturedResponses(array $responses, array $result): void
    {
        $host = self::firstServerKey($result);
        $actual = $this->queryTest($host, 'ruinstofortress', $responses);
        self::assertSame($result[$host], $actual);
        $server = new Server(['type' => 'ruinstofortress', 'host' => $host]);
        $normalized = (new Normalize())->apply($actual, $server);
        self::assertTrue($normalized['gq_online']);
        self::assertSame(0, $normalized['gq_numplayers']);
        self::assertSame('0.9.1', $normalized['version']);
        self::assertSame(2585860, $normalized['game_id']);
        self::assertSame(11, $normalized['num_rules']);
        self::assertSame($actual['GameMode'], $normalized['gq_gametype']);
        self::assertArrayNotHasKey('gq_mapname', $normalized);
        self::assertSame('', $normalized['gq_joinlink']);
    }

    public function testDefaultQueryPortDoesNotGuessCustomPortRelationships(): void
    {
        $server = new Server(['type' => 'ruinstofortress', 'host' => '127.0.0.1:7777']);
        self::assertSame(27015, $server->portQuery());
        self::assertSame(Protocol::STATE_BETA, $server->protocolInstance()->state());
        self::assertNull($server->getJoinLink());
        $server = new Server(['type' => 'ruinstofortress', 'host' => '127.0.0.1:8000']);
        self::assertSame(27015, $server->portQuery());
        $server = new Server([
            'type' => 'ruinstofortress', 'host' => '127.0.0.1:8000', 'options' => ['query_port' => 27238],
        ]);
        self::assertSame(27238, $server->portQuery());
    }

    public function testNoResponseDoesNotClaimOnline(): void
    {
        self::assertFalse($this->queryTest('127.0.0.1:7777', 'ruinstofortress', [])['gq_online']);
    }
}
