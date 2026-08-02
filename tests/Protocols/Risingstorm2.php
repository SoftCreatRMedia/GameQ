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
 * Test Class for Rising Storm 2
 *
 * @package GameQ\Tests\Protocols
 */
class Risingstorm2 extends Base
{
    /**
     * Holds stub on setup
     *
     * @var \GameQ\Protocols\Risingstorm2
     */
    protected \GameQ\Protocols\Risingstorm2 $stub;

    /**
     * Setup
     *
     */
    #[\PHPUnit\Framework\Attributes\Before]
    public function customSetUp(): void
    {
        // Create the stub class
        $this->stub = new \GameQ\Protocols\Risingstorm2();
    }

    /**
     * Test to make sure the query port has not changed.  Appears to be fixed at 27015 but unsure.
     */
    public function testQueryPort(): void
    {
        self::assertSame(27015, $this->stub->findQueryPort(7777));
    }

    /**
     * Test responses for Medal of honor: Allied Assault
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
            'risingstorm2',
            $responses,
        );

        self::assertEqualsDelta($result[$server], $testResult, 0.000000001);
    }
}
