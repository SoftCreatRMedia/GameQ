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
 * Renegade X official master-server protocol.
 *
 * @see https://serverlist-rx.totemarts.services/servers.jsp
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Renegadex extends OfficialDirectory
{
    protected string $protocol = 'renegadex';
    protected string $name = 'renegadex';
    protected string $name_long = 'Renegade X';
    protected array $normalize = ['general' => [
        'hostname' => 'name', 'mapname' => 'Current Map', 'maxplayers' => 'player_limit',
        'numplayers' => 'Players', 'password' => 'passworded',
    ]];

    protected function directoryUrl(): string
    {
        return 'https://serverlist-rx.totemarts.services/servers.jsp';
    }

    /** @return array<string, mixed>|null */
    protected function findServer(mixed $response, Server $server): ?array
    {
        if (!is_array($response)) {
            return null;
        }

        $directoryAddress = $this->options['directory_address'] ?? $server->ip();

        foreach ($response as $entry) {
            $entry = $this->normalizeStringKeyedArray($entry);

            if (
                ($entry['IP'] ?? null) === $directoryAddress
                && $this->normalizeInteger($entry['Port'] ?? null, -1) === $server->portQuery()
            ) {
                return $entry;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function processResponse(): array
    {
        if ($this->serverData === null) {
            return [];
        }

        $variables = $this->normalizeStringKeyedArray($this->serverData['Variables'] ?? null);
        $result = new Result();
        $prefix = is_string($this->serverData['NamePrefix'] ?? null) ? trim($this->serverData['NamePrefix']) : '';
        $name = is_string($this->serverData['Name'] ?? null) ? $this->serverData['Name'] : '';

        foreach ($this->serverData as $key => $value) {
            if ($key !== 'Variables' && $key !== 'Name') {
                $result->add($key, $value);
            }
        }

        $result->add('name', trim($prefix . ' ' . $name));
        $result->add('player_limit', $this->normalizeInteger($variables['Player Limit'] ?? null));
        $result->add('passworded', (bool) ($variables['bPassworded'] ?? false));

        foreach ($variables as $key => $value) {
            $result->add('variable_' . strtolower(str_replace(' ', '_', $key)), $value);
        }

        return $result->fetch();
    }
}
