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

use GameQ\Exception\ServerException;
use GameQ\Filters\Normalize;
use GameQ\Protocol;
use GameQ\Server;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Nuclearoption extends Base
{
    /**
     * @param list<string> $responses
     * @param non-empty-array<string, array<string, mixed>> $result
     */
    #[DataProvider('loadData')]
    public function testCapturedResponses(array $responses, array $result): void
    {
        $host = self::firstServerKey($result);
        $options = ['query_port' => $host === '127.0.0.1:7777' ? 7778 : 15078];
        $actual = $this->queryTest($host, 'nuclearoption', $responses, serverOptions: $options);
        self::assertSame($result[$host], $actual);

        $server = new Server(['type' => 'nuclearoption', 'host' => $host, 'options' => $options]);
        $normalized = (new Normalize())->apply($actual, $server);
        self::assertTrue($normalized['gq_online']);
        self::assertSame('GameQ Nuclear Option Local Test', $normalized['gq_hostname']);
        self::assertSame(0, $normalized['gq_numplayers']);
        self::assertSame($host === '127.0.0.1:7777' ? 8 : 4, $normalized['gq_maxplayers']);
        self::assertSame($host === '127.0.0.1:7777' ? 'Escalation' : 'Terminal Control', $normalized['gq_mapname']);
        self::assertSame($host === '127.0.0.1:7777' ? 0 : 1, $normalized['gq_password']);
        self::assertSame('0.34.6', $normalized['version']);
        self::assertSame(2168680, $normalized['game_id']);
        self::assertSame(0, $normalized['num_bots']);
        self::assertSame('d', $normalized['gq_dedicated']);
        self::assertSame(1, $normalized['secure']);
        self::assertSame(8, $normalized['num_rules']);
        self::assertNull($server->getJoinLink());
        self::assertSame('', $normalized['gq_joinlink']);
    }

    public function testDefaultAndIndependentlyConfiguredQueryPorts(): void
    {
        $server = new Server(['type' => 'nuclearoption', 'host' => '127.0.0.1:7777']);
        self::assertSame(7778, $server->portQuery());
        self::assertSame(Protocol::STATE_BETA, $server->protocolInstance()->state());
        self::assertNull($server->getJoinLink());

        $server = new Server(['type' => 'nuclearoption', 'host' => '127.0.0.1:14077']);
        self::assertSame(14078, $server->portQuery());
        $server = new Server([
            'type' => 'nuclearoption', 'host' => '127.0.0.1:14077', 'options' => ['query_port' => 15078],
        ]);
        self::assertSame(14077, $server->portClient());
        self::assertSame(15078, $server->portQuery());
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidQueryPorts(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'too large' => [65536];
        yield 'null' => [null];
        yield 'boolean' => [true];
        yield 'fraction' => [7778.5];
        yield 'mixed text' => ['7778abc'];
        yield 'empty' => [''];
        yield 'overflow' => [str_repeat('9', 40)];
    }

    #[DataProvider('invalidQueryPorts')]
    public function testInvalidQueryPortsAreRejected(mixed $port): void
    {
        $this->expectException(ServerException::class);
        new Server(['type' => 'nuclearoption', 'host' => '127.0.0.1:7777', 'options' => ['query_port' => $port]]);
    }

    public function testDerivedQueryPortCannotOverflow(): void
    {
        $this->expectException(ServerException::class);
        new Server(['type' => 'nuclearoption', 'host' => '127.0.0.1:65535']);
    }

    public function testUnansweredQueriesStayOffline(): void
    {
        self::assertFalse($this->queryTest('127.0.0.1:7777', 'nuclearoption', [])['gq_online']);
    }
}
