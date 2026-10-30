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
use GameQ\Http\ClientInterface;
use GameQ\Http\Request;
use GameQ\Server;
use WeakMap;

/**
 * Public Chupathingy Delta List. Experimental; unrelated to Halo's Gamespy query.
 *
 * @see https://halo.milenko.org/api
 */
class Chupathingy extends VirtualHttp
{
    private const DIRECTORY_URL = 'https://stats.chupathingy.org/v1/games.txt?fields=22';
    private const MAX_BYTES = 32768;
    private const MAX_RECORDS = 64;
    private const MODES = [1 => 'Capture the Flag', 2 => 'Slayer', 3 => 'Oddball', 4 => 'King of the Hill', 5 => 'Race', 6 => 'Bomb'];

    protected string $protocol = 'chupathingy';
    protected string $name = 'chupathingy';
    protected string $name_long = 'Chupathingy CE Delta List (experimental)';
    protected int $state = self::STATE_BETA;

    protected array $normalize = [
        'general' => [
            'hostname' => 'name', 'mapname' => 'map', 'gametype' => 'game_mode',
            'numplayers' => 'player_count', 'maxplayers' => 'maximum_players', 'dedicated' => 'dedicated',
        ],
        'player' => ['name' => 'name', 'team' => 'team'],
    ];

    /** @var WeakMap<ClientInterface, array{fetched: float, body: string}>|null */
    private static ?WeakMap $cache = null;
    private string $invite = '';
    /** @var array<string, mixed> */
    private array $serverData = [];

    /** @param array<string, mixed> $options */
    public function __construct(array $options = [])
    {
        // Protocol catalogs inspect metadata without configuring a target.
        // A real Server still requires an invite before it can be created.
        if (!array_key_exists('server_invite', $options)) {
            parent::__construct($options);

            return;
        }

        $invite = $options['server_invite'] ?? null;

        if (!is_string($invite) || preg_match('~\A(?:halo://join/)?([a-fA-F0-9]{64})\z~', $invite, $match) !== 1) {
            throw new ProtocolException('Chupathingy requires a 64-hex server_invite or halo://join/<invite>.');
        }

        $this->invite = strtolower($match[1]);
        $options['server_invite'] = $this->invite;
        parent::__construct($options);
    }

    public function targetId(): string
    {
        return $this->name . ':' . $this->requireInvite();
    }

    public function joinLink(): ?string
    {
        return $this->invite !== '' ? 'halo://join/' . $this->invite : null;
    }

    public function beforeSend(Server $server): void
    {
        $invite = $this->requireInvite();
        $this->serverData = [];
        $body = array_key_exists('directory_response', $this->options)
            ? $this->options['directory_response'] : $this->loadDirectory();

        if (!is_string($body) || strlen($body) > self::MAX_BYTES || preg_match('//u', $body) !== 1) {
            return;
        }

        $lines = $body === '' ? [] : explode("\n", rtrim($body, "\r\n"));

        if (count($lines) > self::MAX_RECORDS) {
            return;
        }

        $matches = 0;

        foreach ($lines as $line) {
            $fields = explode("\t", rtrim($line, "\r"));

            if (strtolower($fields[0]) !== $invite) {
                continue;
            }

            if (++$matches > 1) {
                $this->serverData = [];

                return;
            }

            $this->serverData = $this->parseRecord($fields) ?? [];
        }
    }

    private function requireInvite(): string
    {
        if ($this->invite === '') {
            throw new ProtocolException('Chupathingy requires a 64-hex server_invite or halo://join/<invite>.');
        }

        return $this->invite;
    }

    /** @return array<string, mixed> */
    public function processResponse(): array
    {
        return $this->serverData;
    }

    private function loadDirectory(): ?string
    {
        $client = $this->getHttpClient();
        self::$cache ??= new WeakMap();
        $ttl = min(60, max(0, $this->normalizeInteger($this->options['directory_cache_ttl'] ?? 5, 5)));
        $cached = self::$cache[$client] ?? null;

        if ($ttl > 0 && $cached !== null && $cached['fetched'] + $ttl > microtime(true)) {
            return $cached['body'];
        }

        $response = $this->sendHttpRequest(new Request(
            'GET',
            self::DIRECTORY_URL,
            ['Accept' => 'text/plain'],
            timeout: min(30, max(1, $this->normalizeInteger($this->options['http_timeout'] ?? 5, 5))),
            maxResponseBytes: self::MAX_BYTES,
        ));

        if ($response === null || $response->statusCode !== 200 || strlen($response->body) > self::MAX_BYTES) {
            return null;
        }

        if ($ttl > 0) {
            self::$cache[$client] = ['fetched' => microtime(true), 'body' => $response->body];
        }

        return $response->body;
    }

    /**
     * @param list<string> $fields
     *
     * @return array<string, mixed>|null
     */
    private function parseRecord(array $fields): ?array
    {
        if (count($fields) < 8 || count($fields) > 22) {
            return null;
        }

        foreach ($fields as $offset => $field) {
            if (
                strlen($field) > ($offset === 11 ? self::MAX_BYTES : 512)
                || preg_match('/[\x00-\x1F\x7F]/', $field) !== 0
            ) {
                return null;
            }
        }

        if ($fields[1] === '' || strlen($fields[1]) > 256 || strlen($fields[2]) > 512) {
            return null;
        }

        // Unstarted OpenCE listings report engine 0; do not invent a mode.
        $engine = $this->integer($fields[3], 0, 6);
        $count = $this->integer($fields[4], 0, 128);
        $maximum = $this->integer($fields[5], 1, 128);
        $open = $this->integer($fields[6], 0, 1);
        $version = $this->integer($fields[7], 0, 65535);

        if ($engine === null || $count === null || $maximum === null || $count > $maximum || $open === null || $version === null) {
            return null;
        }

        $data = [
            'invite' => $this->invite, 'name' => $fields[1], 'map' => $fields[2], 'engine' => $engine,
            'player_count' => $count, 'maximum_players' => $maximum, 'open' => $open === 1, 'version' => $version,
            'game_mode' => self::MODES[$engine] ?? null,
        ];

        foreach ([8 => 'age', 9 => 'score_limit', 10 => 'team_game'] as $offset => $key) {
            if (isset($fields[$offset])) {
                $value = $this->integer($fields[$offset], 0, $offset === 10 ? 1 : 2147483647);

                if ($value === null) {
                    return null;
                }

                $data[$key] = $value;
            }
        }

        if (isset($fields[11]) && $fields[11] !== '') {
            $players = $this->parseRoster($fields[11], $count);

            if ($players === null) {
                return null;
            }

            $data['players'] = $players;
        }

        $optional = [12 => 'region', 13 => 'platform', 14 => 'hosting', 15 => 'network_protocol',
            16 => 'machines', 17 => 'gametype', 18 => 'capabilities', 19 => 'map_title', 20 => 'map_pack', 21 => 'map_pack_short'];

        foreach ($optional as $offset => $key) {
            if (isset($fields[$offset])) {
                $data[$key] = $fields[$offset];
            }
        }

        if (isset($fields[14]) && $fields[14] !== '') {
            if (!in_array($fields[14], ['official', 'dedicated', 'player'], true)) {
                return null;
            }

            $data['dedicated'] = $fields[14] !== 'player';
        }

        if (isset($fields[15]) && !in_array($fields[15], ['', 'delta', 'opence'], true)) {
            return null;
        }

        if (isset($fields[18]) && $fields[18] !== '' && preg_match('/\A[0-9a-fA-F]{8}\z/', $fields[18]) !== 1) {
            return null;
        }

        if (isset($fields[16]) && $fields[16] !== '') {
            $machines = explode(',', $fields[16]);

            if (count($machines) > 32) {
                return null;
            }

            foreach ($machines as $machine) {
                $parts = explode(':', $machine);

                if (
                    count($parts) !== 2 || preg_match('/\A[a-z][a-z0-9_]{0,31}\z/', $parts[0]) !== 1
                    || $this->integer($parts[1], 0, 128) === null
                ) {
                    return null;
                }
            }
        }

        return $data;
    }

    private function integer(string $value, int $minimum, int $maximum): ?int
    {
        if (preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/', $value) !== 1) {
            return null;
        }

        $number = (int) $value;

        return $number >= $minimum && $number <= $maximum ? $number : null;
    }

    /** @return list<array{name: string, team: int}>|null */
    private function parseRoster(string $roster, int $playerCount): ?array
    {
        $entries = explode('|', $roster);

        if (count($entries) > $playerCount || count($entries) > 128) {
            return null;
        }

        $players = [];

        foreach ($entries as $entry) {
            $parts = explode(':', $entry, 2);

            if (
                count($parts) !== 2 || preg_match('/\A(?:-1|[0-9]{1,3})\z/', $parts[0]) !== 1
                || $parts[1] === '' || strlen($parts[1]) > 256
            ) {
                return null;
            }

            $players[] = ['name' => $parts[1], 'team' => (int) $parts[0]];
        }

        return $players;
    }
}
