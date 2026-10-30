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
 * Kerbal Space Program DarkMultiPlayer JSON status protocol.
 *
 * @see https://github.com/DarklightGames/DarkMultiPlayer/blob/master/Server/ServerInfo.cs
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Kspdmp extends JsonHttp
{
    protected string $protocol = 'kspdmp';
    protected string $name = 'kspdmp';
    protected string $name_long = 'Kerbal Space Program DarkMultiPlayer';
    protected int $port_diff = 1;
    protected array $normalize = ['general' => [
        'hostname' => 'server_name', 'maxplayers' => 'max_players', 'numplayers' => 'player_count',
        'gametype' => 'game_mode',
    ], 'player' => ['name' => 'name']];

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function processJson(array $data): array
    {
        $result = new Result();

        foreach ($data as $key => $value) {
            if ($key !== 'players') {
                $result->add($key, $value);
            }
        }

        $players = $data['players'] ?? [];

        if (is_string($players)) {
            $players = $players === '' ? [] : array_map('trim', explode(',', $players));
        }

        if (!is_array($players)) {
            $players = [];
        }

        foreach ($players as $player) {
            $player = is_string($player) ? ['name' => $player] : $this->normalizeStringKeyedArray($player);

            foreach ($player as $key => $value) {
                $result->addPlayer($key === 'nickname' ? 'name' : $key, $value);
            }
        }

        $result->add('player_count', $this->normalizeInteger($data['player_count'] ?? null, count($players)));

        return $result->fetch();
    }
}
