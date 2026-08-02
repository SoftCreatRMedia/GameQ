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

use GameQ\Protocol;
use GameQ\Result;
use GameQ\Server;
use JsonException;

/**
 * BeamMP server-list protocol.
 *
 * @see https://docs.beammp.com/
 *
 * @author iTeeLion <me@iteelion.ru>
 */
class Beammp extends Protocol
{
    private const BACKEND_URL = 'https://backend.beammp.com/servers/';

    protected string $protocol = 'beammp';

    protected string $name = 'beammp';

    protected string $name_long = 'BeamMP';

    protected int $state = self::STATE_BETA;

    protected array $normalize = [
        'general' => [
            'dedicated' => 'dedicated',
            'hostname' => 'sname',
            'mapname' => 'map',
            'maxplayers' => 'maxplayers',
            'mod' => 'mod',
            'numplayers' => 'players_count',
            'password' => 'password',
        ],
        'player' => [
            'name' => 'name',
        ],
    ];

    /** @var list<array<string, mixed>>|null */
    private static ?array $backendResult = null;

    /** @var array<string, mixed>|null */
    private ?array $serverData = null;

    public function beforeSend(Server $server): void
    {
        $servers = array_key_exists('backend_response', $this->options)
            ? $this->normalizeServerList($this->options['backend_response'])
            : $this->loadBackend();

        foreach ($servers as $serverData) {
            $ip = $serverData['ip'] ?? null;
            $port = $serverData['port'] ?? null;

            if ($ip === $server->ip() && $this->normalizeInteger($port, -1) === $server->portQuery()) {
                $this->serverData = $serverData;

                return;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function processResponse(): array
    {
        if ($this->serverData === null) {
            return [];
        }

        $result = new Result();
        $result->add('dedicated', true);
        $result->add('mod', 'beammp');

        foreach ($this->serverData as $key => $value) {
            if ($key !== 'players' && $key !== 'playerslist') {
                $result->add($key, $value);
            }
        }

        $players = $this->parsePlayers($this->serverData['playerslist'] ?? null);
        $playerCount = $this->normalizeInteger($this->serverData['players'] ?? null, count($players));
        $result->add('players_count', $playerCount);

        foreach ($players as $player) {
            $result->addPlayer('name', $player);
        }

        return $result->fetch();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadBackend(): array
    {
        if (self::$backendResult !== null) {
            return self::$backendResult;
        }

        $handle = curl_init(self::BACKEND_URL);

        if ($handle === false) {
            return self::$backendResult = [];
        }

        $timeout = max(1, $this->normalizeInteger($this->options['http_timeout'] ?? 5, 5));
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
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
            return [];
        }

        try {
            $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        $servers = $this->normalizeServerList($decoded);

        if ($servers !== []) {
            self::$backendResult = $servers;
        }

        return $servers;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeServerList(mixed $servers): array
    {
        if (!is_array($servers)) {
            return [];
        }

        $normalized = [];

        foreach ($servers as $server) {
            $server = $this->normalizeStringKeyedArray($server);

            if ($server !== []) {
                $normalized[] = $server;
            }
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function parsePlayers(mixed $players): array
    {
        if (!is_string($players) || $players === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(';', $players)),
            static fn(string $player): bool => $player !== '',
        ));
    }
}
