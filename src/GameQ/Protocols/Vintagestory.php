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
 * Vintage Story official master-server protocol.
 *
 * @see https://masterserver.vintagestory.at/api/v1/servers/list
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Vintagestory extends OfficialDirectory
{
    protected string $protocol = 'vintagestory';
    protected string $name = 'vintagestory';
    protected string $name_long = 'Vintage Story';
    protected array $normalize = ['general' => [
        'hostname' => 'serverName', 'maxplayers' => 'maxPlayers', 'numplayers' => 'players',
        'password' => 'hasPassword',
    ]];

    protected function directoryUrl(): string
    {
        return 'https://masterserver.vintagestory.at/api/v1/servers/list';
    }

    /** @return array<string, mixed>|null */
    protected function findServer(mixed $response, Server $server): ?array
    {
        $servers = $this->normalizeStringKeyedArray($response)['data'] ?? [];

        if (!is_array($servers)) {
            return null;
        }

        $directoryAddress = $this->options['directory_address'] ?? null;

        foreach ($servers as $entry) {
            $entry = $this->normalizeStringKeyedArray($entry);
            $listedEndpoint = $entry['serverIP'] ?? null;

            if (
                is_string($listedEndpoint)
                && $this->endpointMatches($listedEndpoint, $server, $directoryAddress)
            ) {
                return $entry;
            }
        }

        return null;
    }

    private function endpointMatches(string $listedEndpoint, Server $server, mixed $directoryAddress): bool
    {
        $expectedPort = $server->portQuery();
        $separator = strrpos($listedEndpoint, ':');

        if ($separator === false || (int) substr($listedEndpoint, $separator + 1) !== $expectedPort) {
            return false;
        }

        $listedAddress = trim(substr($listedEndpoint, 0, $separator), '[]');
        $expectedAddresses = [trim($server->ip(), '[]')];

        if (is_string($directoryAddress)) {
            $expectedAddresses[] = trim($directoryAddress, '[]');
        }

        foreach ($expectedAddresses as $expectedAddress) {
            if (strcasecmp($listedAddress, $expectedAddress) === 0) {
                return true;
            }
        }

        return filter_var($listedAddress, FILTER_VALIDATE_IP) === false
            && gethostbyname($listedAddress) === trim($server->ip(), '[]');
    }

    /** @return array<string, mixed> */
    public function processResponse(): array
    {
        if ($this->serverData === null) {
            return [];
        }

        $result = new Result();

        foreach ($this->serverData as $key => $value) {
            $result->add($key, in_array($key, ['players', 'maxPlayers'], true) ? $this->normalizeInteger($value) : $value);
        }

        return $result->fetch();
    }
}
