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

use GameQ\Http\ClientInterface;
use GameQ\Http\Request;
use GameQ\Protocol;
use GameQ\Server;
use JsonException;
use WeakMap;

/**
 * Shared implementation for official HTTPS game-server directories.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
abstract class OfficialDirectory extends Protocol
{
    protected string $transport = self::TRANSPORT_TCP;

    /** @var WeakMap<ClientInterface, array<string, array{expires: int, response: mixed}>>|null */
    private static ?WeakMap $directoryResponses = null;

    /** @var array<string, mixed>|null */
    protected ?array $serverData = null;

    public function beforeSend(Server $server): void
    {
        $response = array_key_exists('directory_response', $this->options)
            ? $this->options['directory_response']
            : $this->loadDirectory();

        $this->serverData = $this->findServer($response, $server);
    }

    abstract protected function directoryUrl(): string;

    /** @return array<string, mixed>|null */
    abstract protected function findServer(mixed $response, Server $server): ?array;

    private function loadDirectory(): mixed
    {
        $url = $this->directoryUrl();
        $cacheTtl = max(0, $this->normalizeInteger($this->options['directory_cache_ttl'] ?? 30, 30));

        $client = $this->getHttpClient();
        self::$directoryResponses ??= new WeakMap();
        $cache = self::$directoryResponses[$client] ?? [];
        $cachedResponse = $cache[$url] ?? null;

        if ($cacheTtl > 0 && $cachedResponse !== null && $cachedResponse['expires'] >= time()) {
            return $cachedResponse['response'];
        }

        if (!str_starts_with($url, 'https://')) {
            return null;
        }

        $timeout = max(1, $this->normalizeInteger($this->options['http_timeout'] ?? 5, 5));
        $response = $this->sendHttpRequest(new Request(
            'GET',
            $url,
            ['Accept' => 'application/json'],
            timeout: $timeout,
            maxResponseBytes: 16 * 1024 * 1024,
        ));

        if ($response === null || $response->statusCode !== 200) {
            return null;
        }

        try {
            $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

            if ($cacheTtl > 0) {
                $cache[$url] = [
                    'expires' => time() + $cacheTtl,
                    'response' => $decoded,
                ];
                self::$directoryResponses[$client] = $cache;
            }

            return $decoded;
        } catch (JsonException) {
            return null;
        }
    }
}
