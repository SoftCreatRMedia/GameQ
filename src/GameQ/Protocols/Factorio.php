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
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace GameQ\Protocols;

use GameQ\Result;
use GameQ\Server;
use JsonException;

/**
 * Factorio matchmaking protocol for publicly listed servers.
 *
 * @see https://wiki.factorio.com/Matchmaking_API
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Factorio extends Http
{
    private const MATCHMAKING_URL = 'https://multiplayer.factorio.com/get-game-details/';

    protected string $protocol = 'factorio';

    protected string $name = 'factorio';

    protected string $name_long = 'Factorio';

    protected array $normalize = [
        'general' => [
            'dedicated' => 'dedicated',
            'hostname' => 'name',
            'maxplayers' => 'max_players',
            'numplayers' => 'players_count',
            'password' => 'has_password',
        ],
        'player' => [
            'name' => 'name',
        ],
    ];

    /** @var array<string, mixed>|null */
    private ?array $serverData = null;

    public function beforeSend(Server $server): void
    {
        if (array_key_exists('matchmaking_response', $this->options)) {
            $this->serverData = $this->normalizeStringKeyedArray($this->options['matchmaking_response']);

            return;
        }

        $this->serverData = $this->loadServer($server);
    }

    /**
     * @return array<string, mixed>
     */
    public function processResponse(): array
    {
        if ($this->serverData === null || !isset($this->serverData['name'])) {
            return [];
        }

        $result = new Result();
        $result->add('dedicated', true);

        foreach ($this->serverData as $key => $value) {
            if ($key !== 'players' && $key !== 'application_version') {
                $result->add($key, $value);
            }
        }

        $players = $this->normalizePlayers($this->serverData['players'] ?? null);
        $result->add('players_count', count($players));

        foreach ($players as $player) {
            $result->addPlayer('name', $player);
        }

        $version = $this->normalizeStringKeyedArray($this->serverData['application_version'] ?? null);

        foreach ($version as $key => $value) {
            $result->add($key, $value);
        }

        return $result->fetch();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadServer(Server $server): ?array
    {
        $handle = curl_init(self::MATCHMAKING_URL . rawurlencode($server->ip() . ':' . $server->portQuery()));

        if ($handle === false) {
            return null;
        }

        $timeout = max(1, $this->normalizeInteger($this->options['http_timeout'] ?? 5, 5));
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_MAXFILESIZE => 8 * 1024 * 1024,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $response = curl_exec($handle);
        $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        if (!is_string($response) || $statusCode !== 200) {
            return null;
        }

        try {
            return $this->normalizeStringKeyedArray(json_decode($response, true, 512, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function normalizePlayers(mixed $players): array
    {
        if (!is_array($players)) {
            return [];
        }

        return array_values(array_filter($players, is_string(...)));
    }
}
