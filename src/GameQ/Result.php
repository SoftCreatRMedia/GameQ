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
     */
    protected array $result = [];

    /**
     * Adds variable to results
     */
    public function add(string $name, mixed $value): void
    {
        $this->result[$name] = $value;
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
        // Nothing of this type yet, set an empty array
        if (!isset($this->result[$sub]) || !is_array($this->result[$sub])) {
            $this->result[$sub] = [];
        }

        // Find the first entry that doesn't have this variable
        $found = false;
        $count = count($this->result[$sub]);
        foreach ($this->result[$sub] as $i => $iValue) {
            if (!isset($iValue[$key])) {
                $this->result[$sub][$i][$key] = $value;
                $found = true;
                break;
            }
        }

        // Not found, create a new entry
        if (!$found) {
            $this->result[$sub][][$key] = $value;
        }

        unset($count);
    }

    /**
     * Return all stored results
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
