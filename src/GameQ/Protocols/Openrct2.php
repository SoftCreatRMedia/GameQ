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
 * OpenRCT2 official master-server protocol.
 *
 * @see https://github.com/OpenRCT2/OpenRCT2/blob/develop/src/openrct2/network/ServerList.cpp
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Openrct2 extends OfficialDirectory
{
    protected string $protocol = 'openrct2';
    protected string $name = 'openrct2';
    protected string $name_long = 'OpenRCT2';
    protected array $normalize = ['general' => [
        'hostname' => 'name', 'maxplayers' => 'maxPlayers', 'numplayers' => 'players',
        'password' => 'requiresPassword', 'version' => 'version',
    ]];

    protected function directoryUrl(): string
    {
        return 'https://servers.openrct2.io';
    }

    /** @return array<string, mixed>|null */
    protected function findServer(mixed $response, Server $server): ?array
    {
        $servers = $this->normalizeStringKeyedArray($response)['servers'] ?? [];

        if (!is_array($servers)) {
            return null;
        }

        $expectedAddresses = [$this->normalizeAddress($server->ip())];
        $directoryAddress = $this->options['directory_address'] ?? null;

        if (is_string($directoryAddress)) {
            $expectedAddresses[] = $this->normalizeAddress($directoryAddress);
        }

        foreach ($servers as $entry) {
            $entry = $this->normalizeStringKeyedArray($entry);

            if ($this->normalizeInteger($entry['port'] ?? null, -1) !== $server->portQuery()) {
                continue;
            }

            $ip = $this->normalizeStringKeyedArray($entry['ip'] ?? null);
            $listedAddresses = array_merge(
                $this->normalizeAddressList($ip['v4'] ?? null),
                $this->normalizeAddressList($ip['v6'] ?? null),
            );

            if (array_intersect($expectedAddresses, $listedAddresses) !== []) {
                return $entry;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function normalizeAddressList(mixed $addresses): array
    {
        if (!is_array($addresses)) {
            return [];
        }

        $normalized = [];

        foreach ($addresses as $address) {
            if (is_string($address)) {
                $normalized[] = $this->normalizeAddress($address);
            }
        }

        return $normalized;
    }

    private function normalizeAddress(string $address): string
    {
        return strtolower(trim($address, '[]'));
    }

    /** @return array<string, mixed> */
    public function processResponse(): array
    {
        if ($this->serverData === null) {
            return [];
        }

        $result = new Result();

        foreach ($this->serverData as $key => $value) {
            if ($key !== 'gameInfo') {
                $result->add($key, in_array($key, ['port', 'players', 'maxPlayers'], true)
                    ? $this->normalizeInteger($value)
                    : $value);
            }
        }

        $gameInfo = $this->normalizeStringKeyedArray($this->serverData['gameInfo'] ?? null);
        $mapSize = $this->normalizeStringKeyedArray($gameInfo['mapSize'] ?? null);
        $result->add('map_size_x', $this->normalizeInteger($mapSize['x'] ?? null));
        $result->add('map_size_y', $this->normalizeInteger($mapSize['y'] ?? null));

        foreach (['day', 'month', 'guests', 'parkValue', 'cash'] as $key) {
            if (array_key_exists($key, $gameInfo)) {
                $result->add($key === 'parkValue' ? 'park_value' : $key, $this->normalizeInteger($gameInfo[$key]));
            }
        }

        return $result->fetch();
    }
}
