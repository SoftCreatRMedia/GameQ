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
 * Test Class for Call of Duty
 *
 * @package GameQ\Tests\Protocols
 */
class Cod extends Base
{
    /**
     * Test the normalization shared by Call of Duty protocols
     */
    public function testCallOfDutyNormalization(): void
    {
        $expected = [
            'general' => [
                'gametype' => 'g_gametype',
                'hostname' => 'sv_hostname',
                'mapname' => 'mapname',
                'maxplayers' => 'sv_maxclients',
                'mod' => '_Mod',
                'numplayers' => 'clients',
                'password' => ['g_needpass', 'pswrd'],
            ],
            'player' => [
                'name' => 'name',
                'ping' => 'ping',
                'score' => 'frags',
            ],
        ];

        foreach (['Cod', 'Coduo', 'Cod2', 'Cod4', 'Codwaw'] as $protocolName) {
            $className = 'GameQ\\Protocols\\' . $protocolName;
            $protocol = new $className();

            self::assertSame($expected, $protocol->getNormalize());
        }
    }

    /**
     * Test responses for Call of Duty
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
            'cod',
            $responses,
        );

        self::assertEquals($result[$server], $testResult);
    }
}
