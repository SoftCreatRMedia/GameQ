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

/**
 * Unreal Tournament 3 Protocol Class
 *
 * Note: The response from UT3 appears to not be consistent.  Many times packets are incomplete or there are extra
 * "echoes" in the responses.  This may cause issues like odd characters showing up in the keys for the player and team
 * array responses. Not sure much can be done about it.
 *
 * @author Austin Bischoff <austin@codebeard.com>
 */
class Ut3 extends Gamespy3
{
    /**
     * String name of this protocol class
     */
    protected string $name = 'ut3';

    /**
     * Longer string name of this protocol class
     */
    protected string $name_long = "Unreal Tournament 3";

    /**
     * Normalize settings for this protocol
     */
    protected array $normalize = [
        // General
        'general' => [
            'dedicated'  => 'bIsDedicated',
            'hostname'   => 'hostname',
            'numplayers' => 'numplayers',
        ],
    ];

    /**
     * Overload the response process so we can make some changes
     *
     * @return mixed
     */
    public function processResponse(): mixed
    {
        // Grab the result from the parent
        $result = parent::processResponse();

        // Move some stuff around
        $this->renameResult($result, 'OwningPlayerName', 'hostname');
        $this->renameResult($result, 'p1073741825', 'mapname');
        $this->renameResult($result, 'p1073741826', 'gametype');
        $this->renameResult($result, 'p1073741827', 'servername');
        $this->renameResult($result, 'p1073741828', 'custom_mutators');
        $this->renameResult($result, 'gamemode', 'open');
        $this->renameResult($result, 's32779', 'gamemode');
        $this->renameResult($result, 's0', 'bot_skill');
        $this->renameResult($result, 's6', 'pure_server');
        $this->renameResult($result, 's7', 'password');
        $this->renameResult($result, 's8', 'vs_bots');
        $this->renameResult($result, 's10', 'force_respawn');
        $this->renameResult($result, 'p268435704', 'frag_limit');
        $this->renameResult($result, 'p268435705', 'time_limit');
        $this->renameResult($result, 'p268435703', 'numbots');
        $this->renameResult($result, 'p268435717', 'stock_mutators');

        // Put custom mutators into an array
        if (isset($result['custom_mutators'])) {
            $result['custom_mutators'] = explode("\x1c", $result['custom_mutators']);
        }

        // Delete some unknown stuff
        $this->deleteResult($result, ['s1', 's9', 's11', 's12', 's13', 's14']);

        // Return the result
        return $result;
    }

    /**
     * Dirty hack to rename result entries into something more useful
     */
    protected function renameResult(array &$result, string $old, string $new): void
    {
        // Check to see if the old item is there
        if (isset($result[$old])) {
            $result[$new] = $result[$old];
            unset($result[$old]);
        }
    }

    /**
     * Dirty hack to delete result items
     */
    protected function deleteResult(array &$result, array $array): void
    {
        foreach ($array as $key) {
            unset($result[$key]);
        }
    }
}
