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
use GameQ\Server;

/**
 * Luanti (formerly Minetest) official server-list protocol.
 *
 * @see https://servers.minetest.net/
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Minetest extends OfficialDirectory
{
    protected string $protocol = 'minetest';
    protected string $name = 'minetest';
    protected string $name_long = 'Luanti (formerly Minetest)';
    protected array $normalize = ['general' => [
        'hostname' => 'name', 'maxplayers' => 'clients_max', 'numplayers' => 'clients',
        'password' => 'password',
    ], 'player' => ['name' => 'name']];

    protected function directoryUrl(): string
    {
        return 'https://servers.minetest.net/list';
    }

    /** @return array<string, mixed>|null */
    protected function findServer(mixed $response, Server $server): ?array
    {
        $document = $this->normalizeStringKeyedArray($response);
        $servers = $document['list'] ?? [];

        if (!is_array($servers)) {
            return null;
        }

        $directoryAddress = $this->options['directory_address'] ?? null;

        foreach ($servers as $entry) {
            $entry = $this->normalizeStringKeyedArray($entry);
            $entryAddress = $entry['address'] ?? null;

            if (!is_string($entryAddress)) {
                continue;
            }

            if (
                $this->addressMatches($entryAddress, $server->ip(), $directoryAddress)
                && $this->normalizeInteger($entry['port'] ?? null, -1) === $server->portQuery()
            ) {
                return $entry;
            }
        }

        return null;
    }

    private function addressMatches(string $listedAddress, string $serverIp, mixed $directoryAddress): bool
    {
        $listedAddress = trim($listedAddress, '[]');
        $serverIp = trim($serverIp, '[]');

        if (
            is_string($directoryAddress)
            && strcasecmp($listedAddress, trim($directoryAddress, '[]')) === 0
        ) {
            return true;
        }

        if ($listedAddress === $serverIp) {
            return true;
        }

        return filter_var($listedAddress, FILTER_VALIDATE_IP) === false
            && gethostbyname($listedAddress) === $serverIp;
    }

    /** @return array<string, mixed> */
    public function processResponse(): array
    {
        if ($this->serverData === null) {
            return [];
        }

        $result = new Result();

        foreach ($this->serverData as $key => $value) {
            if ($key !== 'clients_list') {
                $result->add($key, $value);
            }
        }

        $players = is_array($this->serverData['clients_list'] ?? null) ? $this->serverData['clients_list'] : [];
        $result->add('clients', $this->normalizeInteger($this->serverData['clients'] ?? null, count($players)));

        foreach ($players as $player) {
            if (is_string($player)) {
                $result->addPlayer('name', $player);
            }
        }

        return $result->fetch();
    }
}
