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

use GameQ\Exception\ProtocolException;
use GameQ\Result;
use GameQ\Server;
use JsonException;

/**
 * WARDOGS dedicated server WDRCON API.
 *
 * WDRCON is an authenticated administration API. Even though this protocol
 * only uses read-only endpoints, the configured Bearer token grants broader
 * server administration access and must be treated as a secret.
 *
 * @see http://rcon.wardogs.com/
 * @see https://github.com/warcon-app/warcon/blob/main/docs/wardogs-api.md
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Wardogs extends Http
{
    private const MAX_RESPONSE_BYTES = 2 * 1024 * 1024;

    protected string $protocol = 'wardogs';

    protected string $name = 'wardogs';

    protected string $name_long = 'WARDOGS';

    protected array $normalize = [
        'general' => [
            'dedicated' => 'dedicated',
            'hostname' => 'server_name',
            'mapname' => 'map',
            'maxplayers' => 'max_players',
            'numplayers' => 'num_players',
        ],
        'player' => [
            'name' => 'name',
            'kills' => 'kills',
            'deaths' => 'deaths',
            'score' => 'kills',
            'ping' => 'ping',
        ],
        'team' => [
            'name' => 'name',
            'score' => 'score',
        ],
    ];

    /** @var array<string, array<string, mixed>> */
    private array $apiResponses = [];

    /**
     * WDRCON does not have a reliable relationship between its port and the
     * game's client port. A separate `query_port` is therefore mandatory.
     *
     * @throws ProtocolException
     */
    public function beforeSend(Server $server): void
    {
        if (array_key_exists('api_responses', $this->options)) {
            $this->apiResponses = $this->normalizeApiResponses($this->options['api_responses']);

            return;
        }

        $queryPort = $this->options[Server::SERVER_OPTIONS_QUERY_PORT] ?? null;

        if ($queryPort === null || $queryPort === '') {
            throw new ProtocolException(
                static::class . "::beforeSend Missing required setting '" . Server::SERVER_OPTIONS_QUERY_PORT . "'.",
            );
        }

        $password = $this->options['rcon_password'] ?? null;

        if (!is_string($password) || $password === '') {
            throw new ProtocolException(static::class . "::beforeSend Missing required setting 'rcon_password'.");
        }

        $scheme = $this->options['rcon_scheme'] ?? 'http';

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new ProtocolException(
                static::class . "::beforeSend Setting 'rcon_scheme' must be either 'http' or 'https'.",
            );
        }

        $address = trim($server->ip(), '[]');
        $host = str_contains($address, ':') ? '[' . $address . ']' : $address;
        $baseUrl = sprintf('%s://%s:%d/v1/', $scheme, $host, $server->portQuery());

        foreach (['status', 'capabilities'] as $endpoint) {
            $response = $this->request($baseUrl . $endpoint, $password, $scheme);

            if ($response !== null) {
                $this->apiResponses[$endpoint] = $response;
            }
        }

        if (!isset($this->apiResponses['status'])) {
            return;
        }

        $routes = $this->capabilityRoutes($this->apiResponses['capabilities'] ?? []);

        foreach (['players', 'health', 'server-id'] as $endpoint) {
            if ($routes !== [] && !in_array('/v1/' . $endpoint, $routes, true)) {
                continue;
            }

            $response = $this->request($baseUrl . $endpoint, $password, $scheme);

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
        $status = $this->apiResponses['status'] ?? [];
        $serverName = $status['serverName'] ?? null;

        if (!is_string($serverName) || $serverName === '') {
            return [];
        }

        $result = new Result();
        $result->add('dedicated', true);
        $result->add('server_name', $serverName);

        foreach (['map', 'lighting', 'alternator'] as $key) {
            if (is_string($status[$key] ?? null)) {
                $result->add($key, $status[$key]);
            }
        }

        $experiences = $this->normalizeStringList($status['experiences'] ?? null);

        if ($experiences !== []) {
            $result->add('experiences', $experiences);
        }

        $players = $this->normalizeStringKeyedArray($status['players'] ?? null);
        $result->add('num_players', max(0, $this->normalizeInteger($players['current'] ?? null)));
        $result->add('max_players', max(0, $this->normalizeInteger($players['max'] ?? null)));

        $scoreTick = $this->normalizeStringKeyedArray($status['scoreTick'] ?? null);

        foreach (['current', 'min', 'max'] as $key) {
            if (is_numeric($scoreTick[$key] ?? null)) {
                $result->add('score_tick_' . $key, (float) $scoreTick[$key]);
            }
        }

        foreach (['scoreCap' => 'score_cap', 'matchSeconds' => 'match_seconds'] as $source => $target) {
            if (is_numeric($status[$source] ?? null)) {
                $result->add($target, (float) $status[$source]);
            }
        }

        $rotation = $this->normalizeStringKeyedArray($status['rotation'] ?? null);

        foreach (['nowIndex' => 'rotation_now_index', 'nextIndex' => 'rotation_next_index'] as $source => $target) {
            if (is_numeric($rotation[$source] ?? null)) {
                $result->add($target, (int) $rotation[$source]);
            }
        }

        $this->addTeams($result, $status['factionScores'] ?? null);
        $this->addPlayers($result, $this->apiResponses['players']['players'] ?? null);
        $this->addMetadata($result);

        return $result->fetch();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function request(string $url, string $password, string $scheme): ?array
    {
        $handle = curl_init($url);

        if ($handle === false) {
            return null;
        }

        $response = '';
        $timeout = max(1, $this->normalizeInteger($this->options['http_timeout'] ?? 5, 5));
        $allowedProtocol = $scheme === 'https' ? CURLPROTO_HTTPS : CURLPROTO_HTTP;

        try {
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_PROTOCOLS => $allowedProtocol,
                CURLOPT_REDIR_PROTOCOLS => $allowedProtocol,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXFILESIZE => self::MAX_RESPONSE_BYTES,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Authorization: Bearer ' . $password,
                ],
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$response): int {
                    if (strlen($response) + strlen($chunk) > self::MAX_RESPONSE_BYTES) {
                        return 0;
                    }

                    $response .= $chunk;

                    return strlen($chunk);
                },
            ]);

            $success = curl_exec($handle);
            $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

            if ($success === false || $statusCode !== 200) {
                return null;
            }

            try {
                return $this->normalizeStringKeyedArray(json_decode($response, true, 512, JSON_THROW_ON_ERROR));
            } catch (JsonException) {
                return null;
            }
        } finally {
            unset($handle);
        }
    }

    /**
     * @param array<string, mixed> $capabilities
     * @return list<string>
     */
    private function capabilityRoutes(array $capabilities): array
    {
        $routes = $capabilities['routes'] ?? null;

        if (!is_array($routes)) {
            return [];
        }

        $normalized = [];

        foreach ($routes as $route) {
            if (is_string($route)) {
                if (preg_match('~(?:GET\s+)?(/v1/[a-z-]+)~i', $route, $matches) === 1) {
                    $normalized[] = strtolower($matches[1]);
                }

                continue;
            }

            $route = $this->normalizeStringKeyedArray($route);
            $path = $route['path'] ?? $route['route'] ?? null;

            if (is_string($path) && str_starts_with($path, '/v1/')) {
                $normalized[] = strtolower($path);
            }
        }

        return array_values(array_unique($normalized));
    }

    private function addPlayers(Result $result, mixed $players): void
    {
        if (!is_array($players)) {
            return;
        }

        foreach ($players as $player) {
            $player = $this->normalizeStringKeyedArray($player);
            $name = $player['name'] ?? null;

            if (!is_string($name) || $name === '') {
                continue;
            }

            $result->addPlayer('name', $name);

            foreach (['steamId' => 'steam_id', 'faction' => 'faction'] as $source => $target) {
                if (is_string($player[$source] ?? null)) {
                    $result->addPlayer($target, $player[$source]);
                }
            }

            foreach (['kills', 'deaths', 'cash', 'pingMs'] as $source) {
                if (is_numeric($player[$source] ?? null)) {
                    $target = $source === 'pingMs' ? 'ping' : $source;
                    $result->addPlayer($target, (int) $player[$source]);
                }
            }
        }
    }

    private function addTeams(Result $result, mixed $teams): void
    {
        if (!is_array($teams)) {
            return;
        }

        foreach ($teams as $team) {
            $team = $this->normalizeStringKeyedArray($team);
            $name = $team['name'] ?? null;

            if (!is_string($name) || $name === '') {
                continue;
            }

            $result->addTeam('name', $name);

            if (is_string($team['colorHex'] ?? null)) {
                $result->addTeam('color_hex', $team['colorHex']);
            }

            if (is_numeric($team['score'] ?? null)) {
                $result->addTeam('score', (float) $team['score']);
            }
        }
    }

    private function addMetadata(Result $result): void
    {
        $capabilities = $this->apiResponses['capabilities'] ?? [];

        foreach (['apiVersion' => 'api_version', 'build' => 'build'] as $source => $target) {
            if (is_int($capabilities[$source] ?? null) || is_string($capabilities[$source] ?? null)) {
                $result->add($target, $capabilities[$source]);
            }
        }

        $health = $this->apiResponses['health'] ?? [];

        if (is_string($health['status'] ?? null)) {
            $result->add('health_status', $health['status']);
        }

        if (is_numeric($health['uptimeSeconds'] ?? null)) {
            $result->add('uptime_seconds', (int) $health['uptimeSeconds']);
        }

        $connections = $this->normalizeStringKeyedArray($health['connections'] ?? null);

        if (is_numeric($connections['active'] ?? null)) {
            $result->add('connections_active', (int) $connections['active']);
        }

        $gameThreadQueue = $this->normalizeStringKeyedArray($health['gameThreadQueue'] ?? null);

        foreach (
            [
                'inFlight' => 'game_thread_queue_in_flight',
                'depth' => 'game_thread_queue_depth',
                'rejectedTotal' => 'game_thread_queue_rejected_total',
            ] as $source => $target
        ) {
            if (is_numeric($gameThreadQueue[$source] ?? null)) {
                $result->add($target, (int) $gameThreadQueue[$source]);
            }
        }

        $serverId = $this->apiResponses['server-id']['serverId'] ?? null;

        if (is_string($serverId) && $serverId !== '') {
            $result->add('server_id', $serverId);
        }
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                $normalized[] = $value;
            }
        }

        return $normalized;
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
