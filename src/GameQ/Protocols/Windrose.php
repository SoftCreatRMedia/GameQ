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

use GameQ\Http\Request;
use GameQ\Http\Response;
use GameQ\Protocol;
use GameQ\Result;
use GameQ\Server;
use JsonException;

/**
 * Windrose status protocol provided by the Windrose+ server-side mod.
 *
 * Vanilla Windrose dedicated servers deliberately do not expose a conventional
 * game-server status query. Windrose+ publishes the documented status API used
 * here and must be installed on the server.
 *
 * @see https://playwindrose.com/dedicated-server-guide/
 * @see https://github.com/HumanGenome/WindrosePlus
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Windrose extends Protocol
{
    private const DASHBOARD_PORT = 8780;

    private const MAX_RESPONSE_BYTES = 1024 * 1024;

    protected string $protocol = 'windrose';

    protected string $name = 'windrose';

    protected string $name_long = 'Windrose (requires Windrose+)';

    protected string $transport = self::TRANSPORT_TCP;

    protected int $state = self::STATE_BETA;

    protected array $normalize = [
        'general' => [
            'dedicated' => 'dedicated',
            'hostname' => 'name',
            'maxplayers' => 'max_players',
            'numplayers' => 'player_count',
            'password' => 'password_protected',
        ],
        'player' => [
            'name' => 'name',
        ],
    ];

    /** @var array<string, mixed>|null */
    private ?array $serverData = null;

    /**
     * Windrose+ serves its status API on a dedicated HTTP port rather than the
     * game's direct-connect port. Use the generic `query_port` option to
     * override this value when the dashboard port was changed.
     */
    public function findQueryPort(int $clientPort): int
    {
        return self::DASHBOARD_PORT;
    }

    public function beforeSend(Server $server): void
    {
        $this->serverData = array_key_exists('status_response', $this->options)
            ? $this->normalizeStringKeyedArray($this->options['status_response'])
            : $this->loadServerStatus($server);
    }

    /**
     * @return array<string, mixed>
     */
    public function processResponse(): array
    {
        $data = $this->serverData;

        if ($data === null) {
            return [];
        }

        $server = $this->normalizeStringKeyedArray($data['server'] ?? null);

        if (($server['game'] ?? null) !== 'Windrose' || !is_string($server['windrose_plus'] ?? null)) {
            return [];
        }

        $players = $this->normalizePlayers($data['players'] ?? null);
        $result = new Result();
        $result->add('dedicated', true);
        $result->add('game', 'Windrose');
        $result->add('windrose_plus', $server['windrose_plus']);
        $result->add('max_players', max(0, $this->normalizeInteger($server['max_players'] ?? null)));
        $result->add('player_count', max(0, $this->normalizeInteger($server['player_count'] ?? null, count($players))));

        foreach (['name', 'version', 'invite_code'] as $key) {
            if (is_string($server[$key] ?? null)) {
                $result->add($key, $server[$key]);
            }
        }

        if (is_bool($server['password_protected'] ?? null)) {
            $result->add('password_protected', $server['password_protected']);
        }

        $gamePort = $this->normalizeInteger($server['game_port'] ?? null);

        if ($gamePort >= 1 && $gamePort <= 65535) {
            $result->add('game_port', $gamePort);
        }

        $performance = $this->normalizeStringKeyedArray($data['perf'] ?? null);

        if (is_numeric($performance['world_time'] ?? null)) {
            $result->add('world_time', (float) $performance['world_time']);
        }

        $multipliers = $this->normalizeMultipliers($data['multipliers'] ?? null);

        if ($multipliers !== []) {
            $result->add('multipliers', $multipliers);
        }

        if (is_int($data['timestamp'] ?? null)) {
            $result->add('timestamp', $data['timestamp']);
        }

        foreach ($players as $player) {
            $result->addPlayer('name', $player['name']);

            foreach (['player_id', 'session_id', 'actor_id', 'alive', 'x', 'y', 'z', 'health'] as $key) {
                if (array_key_exists($key, $player)) {
                    $result->addPlayer($key, $player[$key]);
                }
            }
        }

        return $result->fetch();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadServerStatus(Server $server): ?array
    {
        $password = $this->options['dashboard_password'] ?? null;

        if (!is_string($password) || $password === '') {
            return null;
        }

        $address = trim($server->ip(), '[]');
        $host = str_contains($address, ':') ? '[' . $address . ']' : $address;
        $baseUrl = "http://$host:{$server->portQuery()}";
        $timeout = max(1, $this->normalizeInteger($this->options['http_timeout'] ?? 5, 5));
        $login = $this->sendHttpRequest(new Request(
            'POST',
            $baseUrl . '/login',
            ['Accept' => 'text/html', 'Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query(['password' => $password]),
            $timeout,
            self::MAX_RESPONSE_BYTES,
        ));

        if ($login === null || $login->statusCode !== 302) {
            return null;
        }

        $cookies = $this->statusCookies($login, strtolower($address));

        if ($cookies === '') {
            return null;
        }

        $response = $this->sendHttpRequest(new Request(
            'GET',
            $baseUrl . '/api/status',
            ['Accept' => 'application/json', 'Cookie' => $cookies],
            timeout: $timeout,
            maxResponseBytes: self::MAX_RESPONSE_BYTES,
        ));

        if ($response === null || $response->statusCode !== 200) {
            return null;
        }

        try {
            return $this->normalizeStringKeyedArray(json_decode($response->body, true, 512, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            return null;
        }
    }

    /** Keep login cookies local to this server and this query. */
    private function statusCookies(Response $response, string $host): string
    {
        $cookies = [];

        foreach ($response->header('Set-Cookie') as $header) {
            $parts = array_map('trim', explode(';', $header));
            $pair = array_shift($parts);

            if (preg_match('/\A([^=\s,;]+)=([^\r\n;]*)\z/', $pair, $matches) !== 1) {
                continue;
            }

            $attributes = [];

            foreach ($parts as $part) {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
                $attributes[strtolower($key)] = $value;
            }

            $domain = strtolower(ltrim($attributes['domain'] ?? $host, '.'));
            $path = $attributes['path'] ?? '/';
            $expires = isset($attributes['expires']) ? strtotime($attributes['expires']) : false;

            if (
                isset($attributes['secure']) || $domain !== $host
                || ($path !== '/' && $path !== '/api' && $path !== '/api/' && $path !== '/api/status')
                || (isset($attributes['max-age']) && (int) $attributes['max-age'] <= 0)
                || (!isset($attributes['max-age']) && $expires !== false && $expires <= time())
            ) {
                continue;
            }

            $cookies[$matches[1]] = $pair;
        }

        return implode('; ', $cookies);
    }

    /**
     * @return list<array<string, bool|float|int|string>>
     */
    private function normalizePlayers(mixed $players): array
    {
        if (!is_array($players)) {
            return [];
        }

        $normalized = [];

        foreach ($players as $player) {
            $player = $this->normalizeStringKeyedArray($player);
            $name = $player['name'] ?? null;

            if (!is_string($name) || $name === '') {
                continue;
            }

            $entry = ['name' => $name];

            foreach (['player_id', 'session_id', 'actor_id'] as $key) {
                if (is_int($player[$key] ?? null) || is_string($player[$key] ?? null)) {
                    $entry[$key] = $player[$key];
                }
            }

            if (is_bool($player['alive'] ?? null)) {
                $entry['alive'] = $player['alive'];
            }

            foreach (['x', 'y', 'z', 'health'] as $key) {
                if (is_numeric($player[$key] ?? null)) {
                    $entry[$key] = (float) $player[$key];
                }
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @return array<string, float>
     */
    private function normalizeMultipliers(mixed $multipliers): array
    {
        $multipliers = $this->normalizeStringKeyedArray($multipliers);
        $normalized = [];

        foreach (['xp', 'loot', 'craft_efficiency', 'cooking_speed', 'harvest_yield'] as $key) {
            if (is_numeric($multipliers[$key] ?? null)) {
                $normalized[$key] = (float) $multipliers[$key];
            }
        }

        return $normalized;
    }
}
