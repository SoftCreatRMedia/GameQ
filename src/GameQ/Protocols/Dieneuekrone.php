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

use GameQ\Server;

/**
 * Read-only official directory of Die Neue Krone dedicated masters.
 *
 * Target the master's gameplay address (normally UDP 7777), not the private
 * UDP 7778 report channel. Each result represents one master, not one room.
 * No registration, web-admin, or game password is needed or accepted.
 *
 * @see https://haas-lab.de/kaiser-serverlist/api/servers.php
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Dieneuekrone extends OfficialDirectory
{
    protected string $protocol = 'dieneuekrone';
    protected string $name = 'dieneuekrone';
    protected string $name_long = 'Die Neue Krone – Aufstieg zum Kaiser';
    protected int $state = self::STATE_BETA;
    protected ?string $join_link = null;
    protected array $normalize = ['general' => [
        'hostname' => 'server_name', 'numplayers' => 'num_players',
    ]];

    protected function directoryUrl(): string
    {
        return 'https://haas-lab.de/kaiser-serverlist/api/servers.php';
    }

    protected function now(): int
    {
        return time();
    }

    /** @return array<string, mixed>|null */
    protected function findServer(mixed $response, Server $server): ?array
    {
        $now = $this->now();

        if (
            !is_array($response) || ($response['ok'] ?? null) !== true || ($response['schema'] ?? null) !== 1
            || !is_int($response['generated_at'] ?? null)
            || $response['generated_at'] > $now + 30 || $now - $response['generated_at'] > 90
            || !is_array($response['servers'] ?? null) || !array_is_list($response['servers'])
            || count($response['servers']) > 500
        ) {
            return null;
        }

        $targetId = $this->options['server_id'] ?? null;
        $directoryAddress = $this->options['directory_address'] ?? null;

        if (
            ($targetId !== null && (!is_string($targetId) || preg_match('/\A[a-f0-9]{16,64}\z/', $targetId) !== 1))
            || ($directoryAddress !== null && (!is_string($directoryAddress) || $directoryAddress === ''))
        ) {
            return null;
        }

        $seen = [];
        $match = null;

        foreach ($response['servers'] as $entry) {
            if (!is_array($entry) || !is_string($entry['server_id'] ?? null)) {
                return null;
            }

            $id = $entry['server_id'];

            if (preg_match('/\A[a-f0-9]{16,64}\z/', $id) !== 1 || isset($seen[$id])) {
                return null;
            }

            $seen[$id] = true;
            $endpoint = $entry['endpoint'] ?? null;

            if (!is_string($endpoint) || strlen($endpoint) > 270) {
                return null;
            }

            $parts = parse_url('udp://' . $endpoint);

            if (
                $parts === false || !isset($parts['host'], $parts['port'])
                || isset($parts['user']) || isset($parts['pass']) || isset($parts['path'])
                || isset($parts['query']) || isset($parts['fragment'])
                || $parts['port'] < 1
                || !is_int($entry['master_port'] ?? null) || $parts['port'] !== $entry['master_port']
            ) {
                return null;
            }

            $address = trim($parts['host'], '[]');
            $ip = trim($server->ip(), '[]');
            // Do not resolve attacker-controlled directory hostnames. An explicit
            // directory_address can match the advertised DNS spelling instead.
            $addressMatches = strcasecmp($address, is_string($directoryAddress) ? $directoryAddress : $ip) === 0
                || (filter_var($address, FILTER_VALIDATE_IP) !== false && inet_pton($address) === inet_pton($ip));

            if (!$addressMatches || $parts['port'] !== $server->portClient() || ($targetId !== null && $targetId !== $id)) {
                continue;
            }

            if ($match !== null) {
                return null;
            }

            $match = $this->parseEntry($entry, $now);

            if ($match === null) {
                return null;
            }
        }

        return $match;
    }

    /**
     * @param array<array-key, mixed> $entry
     *
     * @return array<string, mixed>|null
     */
    private function parseEntry(array $entry, int $now): ?array
    {
        if (
            !$this->validText($entry['server_name'] ?? null, 240)
            || !is_string($entry['version'] ?? null) || preg_match('/\A\d+\.\d+\.\d+\.\d+\z/', $entry['version']) !== 1
            || !$this->integerInRange($entry['max_games'] ?? null, 1, 32)
            || !$this->integerInRange($entry['room_count'] ?? null, 0, $entry['max_games'])
            || !$this->integerInRange($entry['running_rooms'] ?? null, 0, $entry['room_count'])
            || !$this->integerInRange($entry['players'] ?? null, 0, 256)
            || !is_int($entry['last_seen'] ?? null) || $entry['last_seen'] > $now + 30
            || $now - $entry['last_seen'] > 90 || ($entry['stale'] ?? false) !== false
            || !is_array($entry['rooms'] ?? null) || !array_is_list($entry['rooms'])
            || count($entry['rooms']) !== $entry['room_count']
        ) {
            return null;
        }

        $rooms = [];
        $seen = [];
        $players = 0;
        $running = 0;

        foreach ($entry['rooms'] as $room) {
            if (
                !is_array($room) || !$this->validText($room['id'] ?? null, 320)
                || !$this->validText($room['name'] ?? null, 240)
                || !in_array($room['status'] ?? null, ['starting', 'lobby', 'running'], true)
                || !$this->integerInRange($room['max_players'] ?? null, 1, 8)
                || !$this->integerInRange($room['humans'] ?? null, 0, $room['max_players'])
                || !$this->integerInRange($room['seats'] ?? null, 0, $room['max_players'])
                || !$this->integerInRange($room['year'] ?? null, 0, PHP_INT_MAX)
                || isset($seen[$room['id']])
            ) {
                return null;
            }

            $seen[$room['id']] = true;
            $players += $room['humans'];
            $running += $room['status'] === 'running' ? 1 : 0;
            $rooms[] = array_intersect_key($room, array_flip(['id', 'name', 'status', 'humans', 'seats', 'max_players', 'year']));
        }

        if ($players !== $entry['players'] || $running !== $entry['running_rooms']) {
            return null;
        }

        // Copy only public, documented fields: never expose future credentials
        // or private storage fields through a generic raw response.
        return [
            'server_id' => $entry['server_id'], 'server_name' => $entry['server_name'],
            'master_port' => $entry['master_port'], 'endpoint' => $entry['endpoint'], 'version' => $entry['version'],
            'max_games' => $entry['max_games'], 'room_count' => $entry['room_count'],
            'running_rooms' => $entry['running_rooms'], 'num_players' => $entry['players'], 'rooms' => $rooms,
            'last_seen' => $entry['last_seen'], 'age_seconds' => max(0, $now - $entry['last_seen']),
        ];
    }

    /** @phpstan-assert-if-true non-empty-string $value */
    private function validText(mixed $value, int $max): bool
    {
        return is_string($value) && $value !== '' && strlen($value) <= $max
            && preg_match('//u', $value) === 1 && preg_match('/[\x00-\x1f\x7f]/', $value) === 0;
    }

    /** @phpstan-assert-if-true int $value */
    private function integerInRange(mixed $value, int $min, int $max): bool
    {
        return is_int($value) && $value >= $min && $value <= $max;
    }

    /** @return array<string, mixed> */
    public function processResponse(): array
    {
        return $this->serverData ?? [];
    }
}
