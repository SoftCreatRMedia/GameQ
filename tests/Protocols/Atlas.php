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

use UnexpectedValueException;

/**
 * Test Class for Atlas
 *
 * @package GameQ\Tests\Protocols
 */
class Atlas extends Base
{
    /**
     * Test responses for Atlas
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
        // Splited to later be compared with query port
        $serverParts = explode(":", $server, 2);

        if (!isset($serverParts[1]) || !ctype_digit($serverParts[1])) {
            throw new UnexpectedValueException("Invalid Atlas fixture address '$server'.");
        }

        // Pull the query port of the array
        $queryPort = $result[$server]['gq_port_query'];

        if (!is_int($queryPort)) {
            throw new UnexpectedValueException('The Atlas query port must be an integer.');
        }

        // Here we add the default server port difference to the server game port
        $defaultQueryPort = (int) $serverParts[1] + 51800;

        /**
         * Compare if the port is the same, if not, we should use the custom port in the server query.
         *
         * This is needed to not fail the PHPUnit test, because, the game has a default port difference,
         * but the person hosting the server can change it to a custom port of their choosing,
         * therefor invalidating the default port difference, causing the test to fail.
         *
         * Default port difference: 51800
         * Default query port: gamePort + 51800
         *
         */
        if ($queryPort !== $defaultQueryPort) {
            $options = [
                'query_port' => $queryPort,
            ];
            $testResult = $this->queryTest(
                $server,
                'atlas',
                $responses,
                false,
                $options,
            );
        } else {
            $testResult = $this->queryTest(
                $server,
                'atlas',
                $responses,
            );
        }

        self::assertEqualsDelta($result[$server], $testResult, 0.00000001);
    }
}
