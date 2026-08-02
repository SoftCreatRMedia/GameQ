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

namespace GameQ\Tests\Query;

use GameQ\Exception\QueryException;
use GameQ\Tests\TestBase;
use RuntimeException;

class Native extends TestBase
{
    public function testResponseWaitHonorsOverallTimeout(): void
    {
        $streams = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($streams === false) {
            self::fail('Unable to create the test socket pair.');
        }

        $socket = new \GameQ\Query\Native();
        $socket->socket = $streams[0];
        $query = new \GameQ\Query\Native();
        $startedAt = microtime(true);
        /** @var array<int, array{server_id: string, socket: \GameQ\Query\Core}> $sockets */
        $sockets = [
            (int) $streams[0] => [
                'server_id' => 'timeout-test',
                'socket' => $socket,
            ],
        ];

        try {
            self::assertSame([], $query->getResponses($sockets, 1, 50_000));
        } finally {
            fclose($streams[1]);
            $socket->close();
        }

        self::assertGreaterThanOrEqual(0.8, microtime(true) - $startedAt);
    }

    public function testCompletedResponseBatchStopsAfterAnIdleInterval(): void
    {
        $streams = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($streams === false) {
            self::fail('Unable to create the test socket pair.');
        }

        $socket = new \GameQ\Query\Native();
        $socket->set(\GameQ\Protocol::TRANSPORT_UDP, '127.0.0.1', 1);
        $socket->socket = $streams[0];
        $query = new \GameQ\Query\Native();
        fwrite($streams[1], 'response');
        $startedAt = microtime(true);
        /** @var array<int, array{server_id: string, socket: \GameQ\Query\Core}> $sockets */
        $sockets = [
            (int) $streams[0] => [
                'server_id' => 'response-test',
                'socket' => $socket,
            ],
        ];

        try {
            self::assertSame([
                (int) $streams[0] => ['response'],
            ], $query->getResponses($sockets, 2, 50_000));
        } finally {
            fclose($streams[1]);
            $socket->close();
        }

        self::assertLessThan(0.5, microtime(true) - $startedAt);
    }

    public function testStreamResponseWaitsForDelayedChunks(): void
    {
        $streams = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($streams === false) {
            self::fail('Unable to create the test socket pair.');
        }

        $socket = new \GameQ\Query\Native();
        $socket->set(\GameQ\Protocol::TRANSPORT_TCP, '127.0.0.1', 1);
        $socket->socket = $streams[0];
        $query = new \GameQ\Query\Native();
        fwrite($streams[1], 'response');
        $startedAt = microtime(true);
        /** @var array<int, array{server_id: string, socket: \GameQ\Query\Core}> $sockets */
        $sockets = [
            (int) $streams[0] => [
                'server_id' => 'stream-response-test',
                'socket' => $socket,
            ],
        ];

        try {
            self::assertSame([
                (int) $streams[0] => ['response'],
            ], $query->getResponses($sockets, 2, 50_000));
        } finally {
            fclose($streams[1]);
            $socket->close();
        }

        $duration = microtime(true) - $startedAt;
        self::assertGreaterThanOrEqual(0.8, $duration);
        self::assertLessThan(1.5, $duration);
    }

    public function testWriteFailureBecomesQueryException(): void
    {
        $query = new class extends \GameQ\Query\Native {
            public function get(): mixed
            {
                $stream = fopen('php://memory', 'rb');

                if ($stream === false) {
                    throw new RuntimeException('Unable to create the read-only test stream.');
                }

                return $stream;
            }
        };

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageContains('Unable to write the query packet');

        $query->write('query');
    }
}
