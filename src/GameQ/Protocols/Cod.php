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
 * Call of Duty Protocol Class
 *
 * @package GameQ\Protocols
 *
 * @author  Wilson Jesus <>
 */
class Cod extends Quake3
{
    /**
     * Normalize settings shared by Call of Duty protocols
     */
    protected array $normalize = [
        'general' => [
            'gametype'   => 'g_gametype',
            'hostname'   => 'sv_hostname',
            'mapname'    => 'mapname',
            'maxplayers' => 'sv_maxclients',
            'mod'        => '_Mod',
            'numplayers' => 'clients',
            'password'   => ['g_needpass', 'pswrd'],
        ],
        'player'  => [
            'name'  => 'name',
            'ping'  => 'ping',
            'score' => 'frags',
        ],
    ];

    /**
     * String name of this protocol class
     */
    protected string $name = 'cod';

    /**
     * Longer string name of this protocol class
     */
    protected string $name_long = "Call of Duty";
}
