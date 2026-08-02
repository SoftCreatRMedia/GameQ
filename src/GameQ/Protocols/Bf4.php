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

namespace GameQ\Protocols;

use GameQ\Buffer;
use GameQ\Exception\ProtocolException;
use GameQ\Result;

/**
 * Battlefield 4 Protocol class
 *
 * Good place for doc status and info is http://battlelog.battlefield.com/bf4/forum/view/2955064768683911198/
 *
 * @package GameQ\Protocols
 * @author  Austin Bischoff <austin@codebeard.com>
 */
class Bf4 extends Bf3
{
    /**
     * String name of this protocol class
     */
    protected string $name = 'bf4';

    /**
     * Longer string name of this protocol class
     */
    protected string $name_long = "Battlefield 4";

    /**
     * Handle processing details since they are different from BF3
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    protected function processDetails(Buffer $buffer): array
    {
        // Decode into items
        $items = $this->decode($buffer);

        // Set the result to a new result instance
        $result = new Result();

        $index_current = $this->populateCommonDetails($items, $result);

        $this->populateBattlefield3Details($items, $result, $index_current);

        $result->add('blaze_player_count', (int) $items[$index_current + 13]);
        $result->add('blaze_game_state', (int) $items[$index_current + 14]);

        return $result->fetch();
    }
}
