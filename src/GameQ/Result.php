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

namespace GameQ;

/**
 * Provide an interface for easy storage of a parsed server response
 *
 * @author    Aidan Lister   <aidan@php.net>
 * @author    Tom Buskens    <t.buskens@deviation.nl>
 */
class Result
{
    /**
     * Formatted server response
     *
     * @var array<string, mixed>
     */
    protected array $result = [];

    /**
     * Next candidate row for each field in list-shaped player/team results.
     *
     * @var array<string, array<string, int>>
     */
    private array $subPositions = [];

    /**
     * Adds variable to results
     */
    public function add(string $name, mixed $value): void
    {
        $this->result[$name] = $value;
        unset($this->subPositions[$name]);
    }

    /**
     * Adds player variable to output
     */
    public function addPlayer(string $name, mixed $value): void
    {
        $this->addSub('players', $name, $value);
    }

    /**
     * Adds player variable to output
     */
    public function addTeam(string $name, mixed $value): void
    {
        $this->addSub('teams', $name, $value);
    }

    /**
     * Add a variable to a category
     */
    public function addSub(string $sub, string $key, mixed $value): void
    {
        if (!isset($this->result[$sub]) || !is_array($this->result[$sub])) {
            $this->result[$sub] = [];
            unset($this->subPositions[$sub]);
        }

        $entries = &$this->result[$sub];

        // Find the first entry that doesn't have this variable
        $found = false;

        if (array_is_list($entries)) {
            $position = $this->subPositions[$sub][$key] ?? 0;

            for ($entryCount = count($entries); $position < $entryCount; ++$position) {
                if (is_array($entries[$position]) && !isset($entries[$position][$key])) {
                    $entries[$position][$key] = $value;
                    $found = true;

                    if ($value !== null) {
                        $this->subPositions[$sub][$key] = $position + 1;
                    }

                    break;
                }
            }
        } else {
            foreach ($entries as $i => $iValue) {
                if (is_array($iValue) && !isset($iValue[$key])) {
                    $iValue[$key] = $value;
                    $entries[$i] = $iValue;
                    $found = true;

                    break;
                }
            }
        }

        // Not found, create a new entry
        if (!$found) {
            $entries[] = [$key => $value];

            if ($value !== null && array_is_list($entries)) {
                $this->subPositions[$sub][$key] = count($entries);
            }
        }
    }

    /**
     * Return all stored results
     *
     * @return array<string, mixed>
     */
    public function fetch(): array
    {
        return $this->result;
    }

    /**
     * Return a single variable
     */
    public function get(string $var): mixed
    {
        return $this->result[$var] ?? null;
    }
}
