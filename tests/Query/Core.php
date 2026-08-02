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

namespace GameQ\Tests\Query;

use GameQ\Tests\TestBase;
use RuntimeException;

/**
 * Class Core testing
 *
 * @package GameQ\Tests\Query
 */
class Core extends TestBase
{
    /**
     * Test setting the properties for the query core
     */
    public function testSet(): void
    {
        $stub = new class extends \GameQ\Query\Core {
            protected function create(): void
            {
            }

            public function get(): mixed
            {
                $stream = fopen('php://memory', 'r+b');

                if ($stream === false) {
                    throw new RuntimeException('Unable to create the query test stream.');
                }

                return $stream;
            }

            public function write(string|array $data): int
            {
                return 0;
            }

            public function close(): void
            {
            }

            public function getResponses(array $sockets, int $timeout, int $stream_timeout): array
            {
                return [];
            }
        };

        // Set the properties
        $stub->set('tcp', '127.0.0.1', 27015, 5, true);

        // Verify the properties
        self::assertEquals('tcp', $stub->getTransport());

        self::assertEquals('127.0.0.1', $stub->getIp());

        self::assertEquals(27015, $stub->getPort());

        self::assertEquals(5, $stub->getTimeout());

        self::assertTrue($stub->getBlocking());

        // Testing the clone
        $stub_clone = clone $stub;

        // All of these should tbe the defaults now
        self::assertNull($stub_clone->getTransport());

        self::assertNull($stub_clone->getIp());

        self::assertNull($stub_clone->getPort());

        self::assertEquals(3, $stub_clone->getTimeout());

        self::assertFalse($stub_clone->getBlocking());
    }
}
