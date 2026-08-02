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
 * Test Class for Minecraft PE
 *
 * @package GameQ\Tests\Protocols
 */
class Minecraftpe extends Base
{
    /**
     * Use genuine Bedrock/RakNet captures; the historical MinecraftPE fixtures contained Java responses.
     *
     * @return list<array{list<string>, non-empty-array<string, array<string, mixed>>}>
     */
    public static function loadData(): array
    {
        $providers = Minecraftbe::loadData();

        foreach ($providers as &$provider) {
            foreach ($provider[1] as &$result) {
                $result['gq_name'] = 'MinecraftPE';
                $result['gq_type'] = 'minecraftpe';
            }
        }

        unset($provider, $result);

        return $providers;
    }

    /**
     * Test responses for Minecraft PE
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
            'minecraftpe',
            $responses,
        );

        self::assertEquals($result[$server], $testResult);
    }
}
