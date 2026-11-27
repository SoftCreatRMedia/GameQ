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
 */

namespace GameQ\Protocols;

/**
 * Ruins To Fortress official dedicated server (Steam A2S).
 *
 * UDP 7777/27015 was verified with the official 0.9.1 server. No fixed
 * game/query offset is assumed for custom configurations. The reported A2S
 * map is a mode label rather than the actual world or seed.
 *
 * @see https://ruinstofortress.com/wiki/#linux
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Ruinstofortress extends Source
{
    protected string $name = 'ruinstofortress';
    protected string $name_long = 'Ruins To Fortress';
    protected int $state = self::STATE_BETA;
    protected ?string $join_link = null;
    protected array $normalize = [
        'general' => [
            'hostname' => 'hostname', 'gametype' => 'map', 'dedicated' => 'dedicated',
            'numplayers' => 'num_players', 'maxplayers' => 'max_players', 'password' => 'password',
        ],
        'player' => ['name' => 'name', 'score' => 'score', 'time' => 'time'],
    ];

    public function findQueryPort(int $clientPort): int
    {
        return 27015;
    }
}
