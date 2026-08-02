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

namespace GameQ\Filters;

use GameQ\Server;

/**
 * Class Secondstohuman
 *
 * This class converts seconds into a human-readable time string 'hh:mm:ss'. This is mainly for converting
 * a player's connected time into a readable string. Note that most game servers DO NOT return a player's connected
 * time. Source (A2S) based games generally do but not always. This class can also be used to convert other time
 * responses into readable time
 *
 * @package GameQ\Filters
 * @author  Austin Bischoff <austin@codebeard.com>
 */
class Secondstohuman extends Base
{
    /**
     * The options key for setting the data key(s) to look for to convert
     */
    public const OPTION_TIMEKEYS = 'timekeys';

    /**
     * The result key added when applying this filter to a result
     */
    public const RESULT_KEY = 'gq_%s_human';

    /**
     * Holds the default 'time' keys from the response array.  This is key is usually 'time' from A2S responses
     *
     * @var list<string>
     */
    protected array $timeKeysDefault = ['time'];

    /**
     * Secondstohuman constructor.
     *
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        // Check for passed keys
        if (!array_key_exists(self::OPTION_TIMEKEYS, $options)) {
            // Use default
            $options[self::OPTION_TIMEKEYS] = $this->timeKeysDefault;
        } else {
            // Used passed key(s) and make sure it is an array
            $options[self::OPTION_TIMEKEYS] = (!is_array($options[self::OPTION_TIMEKEYS]))
                ? [$options[self::OPTION_TIMEKEYS]] : $options[self::OPTION_TIMEKEYS];
        }

        parent::__construct($options);
    }

    /**
     * Apply this filter to the result data
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function apply(array $result, Server $server): array
    {
        // Send the results off to be iterated and return the updated result
        return $this->iterate($result);
    }

    /**
     * Recursively add human-readable values for configured time keys.
     *
     * @template TKey of array-key
     * @param array<TKey, mixed> $result
     * @return array<TKey, mixed>
     */
    protected function iterate(array $result): array
    {
        $timeKeys = $this->options[self::OPTION_TIMEKEYS] ?? [];

        if (!is_array($timeKeys)) {
            return $result;
        }

        // Iterate over the results
        foreach ($result as $key => $value) {
            // Offload to itself if we have another array
            if (is_array($value)) {
                // Iterate and update the result
                $result[$key] = $this->iterate($value);
            } elseif (
                is_numeric($value)
                && in_array(
                    $key,
                    $timeKeys,
                    true,
                )
            ) {
                // Work with whole seconds. Casting once avoids implicit float-to-int
                // conversions in the modulo operations on PHP 8.1 and newer.
                $seconds = (int) $value;
                // We match one of the keys we are wanting to convert so add it and move on
                $result[sprintf(self::RESULT_KEY, $key)] = sprintf(
                    "%02d:%02d:%02d",
                    intdiv($seconds, 3600),
                    intdiv($seconds, 60) % 60,
                    $seconds % 60,
                );
            }
        }

        return $result;
    }
}
