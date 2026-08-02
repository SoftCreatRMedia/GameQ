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
 * Class Normalize
 *
 * @package GameQ\Filters
 */
class Normalize extends Base
{
    /**
     * Holds the protocol specific normalize information
     *
     * @var array<string, array<string, string|list<string>>>
     */
    protected array $normalize = [];

    /**
     * Apply this filter
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function apply(array $result, Server $server): array
    {
        // No result passed so just return
        if ($result === []) {
            return $result;
        }

        //$data = [ ];
        //$data['raw'][$server->id()] = $result;

        // Grab the normalize for this protocol for the specific server
        $protocol = $server->protocolInstance();
        $this->normalize = $protocol->getNormalize();

        // Do general information
        foreach ($this->check('general', $result) as $property => $value) {
            $result[$property] = $value;
        }

        // Do player information
        if (isset($result['players']) && is_array($result['players']) && $result['players'] !== []) {
            // Iterate
            foreach ($result['players'] as $key => $player) {
                if (is_array($player)) {
                    foreach ($this->check('player', $player) as $property => $value) {
                        $player[$property] = $value;
                    }

                    $result['players'][$key] = $player;
                }
            }
        } else {
            $result['players'] = [];
        }

        // Do team information
        if (isset($result['teams']) && is_array($result['teams']) && $result['teams'] !== []) {
            // Iterate
            foreach ($result['teams'] as $key => $team) {
                if (is_array($team)) {
                    foreach ($this->check('team', $team) as $property => $value) {
                        $team[$property] = $value;
                    }

                    $result['teams'][$key] = $team;
                }
            }
        } else {
            $result['teams'] = [];
        }

        // Return the normalized result
        return $result;
    }

    /**
     * Check a section for normalization
     *
     * @param string $section
     * @param array<array-key, mixed> $data
     * @return array<string, mixed>
     */
    protected function check(string $section, array $data): array
    {
        // Normalized return array
        $normalized = [];

        $rules = $this->normalize[$section] ?? [];

        if ($rules !== []) {
            foreach ($rules as $property => $raw) {
                // Default the value for the new key as null
                $value = null;

                if (is_array($raw)) {
                    // Iterate over the raw property we want to use
                    foreach ($raw as $check) {
                        if (array_key_exists($check, $data)) {
                            $value = $data[$check];

                            break;
                        }
                    }
                } elseif (array_key_exists($raw, $data)) {
                    // String
                    $value = $data[$raw];
                }

                $normalized['gq_' . $property] = $value;
            }
        }

        return $normalized;
    }
}
