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

use GameQ\Result;

/**
 * Build and Shoot JSON status protocol.
 *
 * @see https://github.com/piqueserver/piqueserver/blob/master/piqueserver/statusserver.py
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Buildandshoot extends JsonHttp
{
    protected string $protocol = 'buildandshoot';
    protected string $name = 'buildandshoot';
    protected string $name_long = 'Build and Shoot';
    protected int $port_diff = -1;
    protected string $path = '/json';
    protected array $normalize = ['general' => [
        'hostname' => 'server_name', 'mapname' => 'map_name', 'maxplayers' => 'max_players',
        'numplayers' => 'num_players',
    ], 'player' => ['name' => 'name']];

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function processJson(array $data): array
    {
        $result = new Result();
        $result->add('server_name', $data['serverName'] ?? null);
        $map = $this->normalizeStringKeyedArray($data['map'] ?? null);
        $result->add('map_name', $map['name'] ?? null);
        $result->add('version', $data['serverVersion'] ?? null);
        $players = $this->players($data['players'] ?? null);
        $result->add('num_players', count($players));
        $playerData = $this->normalizeStringKeyedArray($data['players'] ?? null);
        $result->add('max_players', $data['maxPlayers'] ?? ($playerData['maxPlayers'] ?? null));

        foreach ($players as $player) {
            foreach ($player as $key => $value) {
                $result->addPlayer($key, $value);
            }
        }

        $result->add('raw', $data);

        return $result->fetch();
    }

    /** @return list<array<string, mixed>> */
    private function players(mixed $value): array
    {
        $groups = is_array($value) && array_is_list($value) ? [$value] : [];

        if (is_array($value) && !array_is_list($value)) {
            $groups = [$value['blue'] ?? [], $value['green'] ?? []];
        }

        $players = [];

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            foreach ($group as $player) {
                $players[] = is_string($player) ? ['name' => $player] : $this->normalizeStringKeyedArray($player);
            }
        }

        return array_values(array_filter($players, static fn(array $player): bool => isset($player['name'])));
    }
}
