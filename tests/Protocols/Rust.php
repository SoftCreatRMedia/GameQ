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

/**
 * Test Class for Rust
 *
 * @package GameQ\Tests\Protocols
 */
class Rust extends Base
{
    public function testKeywordParsingIsIndependentOfOrder(): void
    {
        $protocol = new class extends \GameQ\Protocols\Rust {
            /**
             * @param list<string> $keywords
             * @return array<string, mixed>
             */
            public function parseForTest(array $keywords): array
            {
                return $this->parseKeywords($keywords);
            }
        };

        self::assertSame([
            'server.keywords' => [
                'cp' => '42',
                'mp' => '100',
                'oxide' => true,
            ],
            'server.tags' => ['weekly'],
            'unhandled.tags' => ['unknown'],
            'region' => 'EU',
        ], $protocol->parseForTest(['cp42', 'weekly', 'EU', 'mp100', 'oxide', 'unknown']));
    }

    /**
     * Test responses for Rust
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
            'rust',
            $responses,
        );

        self::assertEqualsDelta($result[$server], $testResult, 0.000000001);
    }
}
