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
 * LIMP MODE's dedicated-server Steam query socket, not its relay endpoint.
 *
 * Configure the actual query address/port in host (normally UDP 27016).
 * Multiple instances may choose different ports. Remote monitoring requires
 * the operator to deliberately make that socket reachable; no Steam account,
 * relay address or game-join password is used by GameQ.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Limpmode extends Source
{
    protected string $name = 'limpmode';
    protected string $name_long = 'LIMP MODE';
    protected int $state = self::STATE_BETA;
    protected ?string $join_link = null;
    protected array $normalize = [
        'general' => [
            'hostname' => 'hostname', 'gametype' => 'map', 'dedicated' => 'dedicated',
            'numplayers' => 'num_players', 'maxplayers' => 'max_players', 'password' => 'password',
        ],
        'player' => ['name' => 'name', 'score' => 'score', 'time' => 'time'],
    ];
}
