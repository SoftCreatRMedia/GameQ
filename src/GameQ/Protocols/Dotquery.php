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

use GameQ\Buffer;
use GameQ\Protocol;
use GameQ\Server;
use JsonException;

/**
 * Experimental UDP Dot Query Protocol (DQP1), not its separate A2S compatibility mode.
 *
 * @see https://github.com/modcommunity/dot-server-query/blob/main/addons/dot_server_query/PROTOCOL.md
 */
class Dotquery extends Protocol
{
    private const MAX_BODY_BYTES = 1048576;
    private const MAX_FRAGMENTS = 16;
    private const MAX_DATAGRAM = 1200;
    private const MAX_PAYLOAD = 1174;
    private const TYPE_RESULT = 0x81;
    private const TYPE_CHALLENGE = 0x82;
    private const FLAG_GZIP = 1;
    private const FLAG_FRAGMENTED = 2;

    protected string $protocol = 'dotquery';
    protected string $name = 'dotquery';
    protected string $name_long = 'Dot Query Protocol (experimental)';
    protected int $state = self::STATE_BETA;
    protected string $transport = self::TRANSPORT_UDP;
    protected ?string $join_link = null;
    protected array $normalize = [
        'general' => [
            'hostname' => 'hostname', 'mapname' => 'map', 'gametype' => 'game',
            'numplayers' => 'num_players', 'maxplayers' => 'max_players', 'dedicated' => 'dedicated',
            'password' => 'password', 'mod' => 'app',
        ],
        'player' => ['name' => 'name', 'score' => 'score', 'time' => 'duration'],
        'team' => ['name' => 'name'],
    ];

    private int $transaction = 0;
    private string $body = '';
    private bool $challenged = false;
    private string $cookie = '';
    private int $resultStart = 0;
    private ?string $cacheKey = null;
    private ?string $previousKey = null;
    /** @var array<string, mixed>|null */
    private ?array $previousBody = null;

    public function beforeSend(Server $server): void
    {
        $this->packets_response = [];
        $this->challenged = false;
        $this->cookie = '';
        $this->resultStart = 0;
        $this->transaction = (new Buffer(random_bytes(4)))->readInt32();
        $sections = ['info', 'players', 'rules', 'game', 'teams', 'rotation', 'build'];

        foreach (['perf', 'stats', 'player_stats'] as $section) {
            if (($this->options['query_' . $section] ?? false) === true) {
                $sections[] = $section;
            }
        }

        $this->cacheKey = $server->ip() . ':' . $server->portQuery() . '/' . implode(',', $sections);
        $request = ['sections' => $sections];

        if (
            ($this->options['query_conditional'] ?? false) === true && $this->previousKey === $this->cacheKey
            && is_int($this->previousBody['rev'] ?? null)
        ) {
            $request['if_rev'] = $this->previousBody['rev'];
        }

        $this->body = json_encode($request, JSON_THROW_ON_ERROR);
        $this->packets = [self::PACKET_DETAILS => $this->request(str_repeat("\x00", 8))];
    }

    private function request(string $cookie): string
    {
        $flags = ($this->options['query_gzip'] ?? true) === true ? 4 : 0;

        return 'DQP1' . pack('CCVVvv', 1, $flags, $this->transaction, 0, 1, 0) . $cookie . $this->body;
    }

    public function getFollowUpPackets(): array
    {
        if ($this->challenged || $this->packets_response === []) {
            return [];
        }

        if (count($this->packets_response) !== 1) {
            return [];
        }

        $packet = $this->packets_response[0];
        $header = $this->readHeader($packet);

        if (
            $header === null || $header['type'] !== self::TYPE_CHALLENGE || $header['flags'] !== 0
            || $header['count'] !== 1 || $header['index'] !== 0 || strlen($packet) !== 26
            || substr($packet, 18, 8) === str_repeat("\x00", 8)
        ) {
            return [];
        }

        $this->challenged = true;
        $this->cookie = substr($packet, 18, 8);
        $this->resultStart = 1;

        return [$this->request($this->cookie)];
    }

    /** @return array{type: int, flags: int, txn: int, response: int, count: int, index: int}|null */
    private function readHeader(string $packet): ?array
    {
        if (strlen($packet) < 26 || strlen($packet) > self::MAX_DATAGRAM || substr($packet, 0, 4) !== 'DQP1') {
            return null;
        }

        $buffer = new Buffer(substr($packet, 4, 14));
        $header = [
            'type' => $buffer->readInt8(),
            'flags' => $buffer->readInt8(),
            'txn' => $buffer->readInt32(),
            'response' => $buffer->readInt32(),
            'count' => $buffer->readInt16(),
            'index' => $buffer->readInt16(),
        ];

        if (
            $header['txn'] !== $this->transaction || ($header['flags'] & ~3) !== 0
            || $header['count'] < 1 || $header['count'] > self::MAX_FRAGMENTS
            || $header['index'] >= $header['count']
        ) {
            return null;
        }

        return $header;
    }

    public function processResponse(): array
    {
        if (!$this->challenged || count($this->packets_response) <= $this->resultStart) {
            return [];
        }

        $fragments = [];
        $identity = null;

        foreach (array_slice($this->packets_response, $this->resultStart) as $packet) {
            $header = $this->readHeader($packet);

            if (
                $header === null || $header['type'] !== self::TYPE_RESULT
                || substr($packet, 18, 8) !== $this->cookie
                || (($header['flags'] & self::FLAG_FRAGMENTED) !== 0) !== ($header['count'] > 1)
                || isset($fragments[$header['index']])
            ) {
                return [];
            }

            $currentIdentity = [$header['response'], $header['count'], $header['flags']];

            if ($identity !== null && $identity !== $currentIdentity) {
                return [];
            }

            $identity = $currentIdentity;
            $fragments[$header['index']] = substr($packet, 26);
        }

        if ($identity === null || count($fragments) !== $identity[1]) {
            return [];
        }

        ksort($fragments);
        $payload = implode('', $fragments);

        if (strlen($payload) > self::MAX_FRAGMENTS * self::MAX_PAYLOAD) {
            return [];
        }

        if (($identity[2] & self::FLAG_GZIP) !== 0) {
            if (($this->options['query_gzip'] ?? true) !== true || !function_exists('gzdecode')) {
                return [];
            }

            $decoded = @gzdecode($payload, self::MAX_BODY_BYTES + 1);

            if (!is_string($decoded) || strlen($decoded) > self::MAX_BODY_BYTES) {
                return [];
            }

            $payload = $decoded;
        }

        try {
            $body = json_decode($payload, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (!is_array($body) || array_is_list($body)) {
            return [];
        }

        $body = array_filter($body, is_string(...), ARRAY_FILTER_USE_KEY);

        if (($body['unchanged'] ?? false) === true) {
            if (
                ($this->options['query_conditional'] ?? false) !== true || $this->cacheKey !== $this->previousKey
                || $this->previousBody === null || !is_int($body['rev'] ?? null)
                || $body['rev'] !== ($this->previousBody['rev'] ?? null)
            ) {
                return [];
            }

            $body = array_replace($this->previousBody, $body);
        }

        $result = $this->normalizeBody($body);

        if ($result !== []) {
            $this->previousKey = $this->cacheKey;
            $this->previousBody = $body;
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $body
     * @return array<string, mixed>
     */
    private function normalizeBody(array $body): array
    {
        if (
            !is_int($body['rev'] ?? null) || $body['rev'] < 0
            || !is_int($body['ts'] ?? null) || $body['ts'] < 0
        ) {
            return [];
        }

        $sections = $body['sections'] ?? null;

        if (!is_array($sections) || !is_array($sections['info'] ?? null)) {
            return [];
        }

        $info = $sections['info'];

        if (
            !is_string($info['name'] ?? null) || $info['name'] === '' || strlen($info['name']) > 256
            || !is_int($info['players'] ?? null) || $info['players'] < 0
            || !is_int($info['max_players'] ?? null) || $info['max_players'] < $info['players']
            || $info['max_players'] > 65535 || $info['max_players'] < 1
        ) {
            return [];
        }

        foreach (['map', 'game', 'app', 'game_version', 'version', 'server_type'] as $key) {
            if (isset($info[$key]) && (!is_string($info[$key]) || strlen($info[$key]) > 512)) {
                return [];
            }
        }

        foreach (['password', 'secure'] as $key) {
            if (isset($info[$key]) && !is_bool($info[$key])) {
                return [];
            }
        }

        foreach (['game', 'rotation', 'build', 'perf', 'stats', 'player_stats'] as $key) {
            if (isset($sections[$key]) && !is_array($sections[$key])) {
                return [];
            }
        }

        $data = array_filter($body, is_string(...), ARRAY_FILTER_USE_KEY);
        $data['hostname'] = $info['name'];
        $data['num_players'] = $info['players'];
        $data['max_players'] = $info['max_players'];

        foreach (['map', 'game', 'app', 'password'] as $key) {
            if (isset($info[$key])) {
                $data[$key] = $info[$key];
            }
        }

        $data['version'] = $info['game_version'] ?? $info['version'] ?? null;

        if (in_array($info['server_type'] ?? null, ['dedicated', 'listen'], true)) {
            $data['dedicated'] = $info['server_type'] === 'dedicated';
        }

        if (isset($sections['players'])) {
            if (!is_array($sections['players']) || !array_is_list($sections['players']) || count($sections['players']) > 128) {
                return [];
            }

            foreach ($sections['players'] as $player) {
                if (!is_array($player) || !is_string($player['name'] ?? null) || strlen($player['name']) > 256) {
                    return [];
                }

                foreach (['score', 'userid', 'ping'] as $key) {
                    if (isset($player[$key]) && !is_int($player[$key])) {
                        return [];
                    }
                }

                if (
                    isset($player['duration']) && ((!is_int($player['duration']) && !is_float($player['duration']))
                    || !is_finite($player['duration']) || $player['duration'] < 0)
                ) {
                    return [];
                }
            }

            $data['players'] = $sections['players'];
        }

        if (isset($sections['rules'])) {
            if (!is_array($sections['rules'])) {
                return [];
            }

            $data['rules'] = $sections['rules'];
        }

        if (isset($sections['teams'])) {
            if (!is_array($sections['teams']) || count($sections['teams']) > 128) {
                return [];
            }

            $teams = [];

            foreach ($sections['teams'] as $name => $team) {
                if ($name === '_balanced') {
                    continue;
                }

                if (!is_array($team) || !is_int($team['players'] ?? null) || $team['players'] < 0) {
                    return [];
                }

                // json_decode converts numeric JSON object member names to int.
                // These are real upstream team identifiers, not invented labels.
                $teams[] = ['name' => (string) $name] + $team;
            }

            $data['teams'] = $teams;
        }

        return $data;
    }
}
