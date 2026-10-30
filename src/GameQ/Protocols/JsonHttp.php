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
use GameQ\Server;
use JsonException;

/**
 * Shared base for game servers exposing status as JSON over HTTP.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
abstract class JsonHttp extends Http
{
    protected string $path = '/';

    protected array $packets = [self::PACKET_STATUS => ''];

    public function beforeSend(Server $server): void
    {
        $address = trim($server->ip(), '[]');
        $host = str_contains($address, ':') ? '[' . $address . ']' : $address;
        $this->packets[self::PACKET_STATUS] = "GET $this->path HTTP/1.1\r\n"
            . "Host: $host:{$server->portQuery()}\r\n"
            . "Accept: application/json\r\n"
            . "Connection: close\r\n\r\n";
    }

    /** @return array<string, mixed>
     *
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        $body = $this->extractHttpBody(implode('', $this->packets_response), $this->name_long);

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ProtocolException("$this->name_long returned invalid JSON.", 0, $exception);
        }

        if (!is_array($data)) {
            throw new ProtocolException("$this->name_long returned an invalid JSON document.");
        }

        return $this->processJson($this->normalizeStringKeyedArray($data));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    abstract protected function processJson(array $data): array;
}
