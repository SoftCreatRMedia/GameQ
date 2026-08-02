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
 * Palworld REST API Protocol Class
 *
 * The server must have its REST API enabled. Pass the server's AdminPassword
 * as the `admin_password` protocol option. The REST API is intended for use on
 * a trusted network and should not be exposed directly to the internet.
 *
 * @see https://docs.palworldgame.com/api/rest-api/palwold-rest-api/
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Palworld extends Http
{
    protected string $protocol = 'palworld';

    protected string $name = 'palworld';

    protected string $name_long = 'Palworld';

    protected int $port_diff = 1;

    protected array $normalize = [
        'general' => [
            'dedicated' => 'dedicated',
            'hostname' => 'servername',
            'maxplayers' => ['maxplayernum', 'ServerPlayerMaxNum'],
            'numplayers' => 'currentplayernum',
        ],
        'player' => [
            'name' => 'name',
            'ping' => 'ping',
        ],
    ];

    /** @var array<string, array<string, mixed>> */
    private array $apiResponses = [];

    public function beforeSend(Server $server): void
    {
        if (array_key_exists('api_responses', $this->options)) {
            $this->apiResponses = $this->normalizeApiResponses($this->options['api_responses']);

            return;
        }

        $password = $this->options['admin_password'] ?? null;

        if (!is_string($password) || $password === '') {
            return;
        }

        $username = $this->options['rest_username'] ?? 'admin';

        if (!is_string($username) || $username === '') {
            $username = 'admin';
        }

        $baseUrl = sprintf('http://%s:%d/v1/api/', $server->ip(), $server->portQuery());

        foreach (['info', 'players', 'metrics', 'settings'] as $endpoint) {
            $response = $this->request($baseUrl . $endpoint, $username, $password);

            if ($response !== null) {
                $this->apiResponses[$endpoint] = $response;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function processResponse(): array
    {
        $info = $this->apiResponses['info'] ?? [];

        if (!isset($info['servername'])) {
            return [];
        }

        $result = new Result();
        $result->add('dedicated', true);

        foreach (['info', 'metrics', 'settings'] as $endpoint) {
            foreach ($this->apiResponses[$endpoint] ?? [] as $key => $value) {
                $result->add($key, $value);
            }
        }

        $playersResponse = $this->apiResponses['players'] ?? [];
        $players = $playersResponse['players'] ?? [];
        $playerCount = 0;

        if (is_array($players)) {
            foreach ($players as $player) {
                $player = $this->normalizeStringKeyedArray($player);

                if ($player === []) {
                    continue;
                }

                ++$playerCount;

                foreach ($player as $key => $value) {
                    $result->addPlayer($key, $value);
                }
            }
        }

        if (!isset(($this->apiResponses['metrics'] ?? [])['currentplayernum'])) {
            $result->add('currentplayernum', $playerCount);
        }

        return $result->fetch();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function request(string $url, string $username, string $password): ?array
    {
        $handle = curl_init($url);

        if ($handle === false) {
            return null;
        }

        $timeout = max(1, $this->normalizeInteger($this->options['http_timeout'] ?? 5, 5));
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
            CURLOPT_MAXFILESIZE => 8 * 1024 * 1024,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $username . ':' . $password,
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
     * @return array<string, array<string, mixed>>
     */
    private function normalizeApiResponses(mixed $responses): array
    {
        if (!is_array($responses)) {
            return [];
        }

        $normalized = [];

        foreach ($responses as $endpoint => $response) {
            if (!is_string($endpoint)) {
                continue;
            }

            $response = $this->normalizeStringKeyedArray($response);

            if ($response !== []) {
                $normalized[$endpoint] = $response;
            }
        }

        return $normalized;
    }
}
