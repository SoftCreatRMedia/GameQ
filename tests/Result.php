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

namespace GameQ\Tests;

/**
 * Result aggregation tests.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Result extends TestBase
{
    public function testSubResultsRetainTheirRowOrdering(): void
    {
        $result = new \GameQ\Result();

        for ($index = 0; $index < 1000; ++$index) {
            $result->addPlayer('id', $index);
            $result->addPlayer('name', "Player $index");
            $result->addPlayer('score', $index * 10);
        }

        $players = $result->fetch()['players'] ?? null;

        self::assertIsArray($players);
        self::assertCount(1000, $players);
        self::assertSame([
            'id' => 999,
            'name' => 'Player 999',
            'score' => 9990,
        ], $players[999]);
    }

    public function testNullSubValuesRetainLegacyOverwriteBehavior(): void
    {
        $result = new \GameQ\Result();
        $result->addPlayer('name', null);
        $result->addPlayer('name', 'Replacement');

        self::assertSame([
            'players' => [
                ['name' => 'Replacement'],
            ],
        ], $result->fetch());
    }
}
