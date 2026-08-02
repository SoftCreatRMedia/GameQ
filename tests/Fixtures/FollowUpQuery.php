<?php

/**
 * This file is part of GameQ.
 *
 * GameQ is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

namespace GameQ\Tests\Fixtures;

use GameQ\Query\Core;
use RuntimeException;

final class FollowUpQuery extends Core
{
    /** @var list<list<string>> */
    public static array $roundResponses = [];

    /** @var list<string> */
    public static array $writes = [];

    public static function clearWrites(): void
    {
        self::$writes = [];
    }

    protected function create(): void
    {
        $socket = fopen('php://memory', 'r+b');

        if ($socket === false) {
            throw new RuntimeException('Unable to create the follow-up query test socket.');
        }

        $this->socket = $socket;
    }

    public function get(): mixed
    {
        if (!is_resource($this->socket ?? null)) {
            $this->create();
        }

        if (!is_resource($this->socket)) {
            throw new RuntimeException('Unable to retrieve the follow-up query test socket.');
        }

        return $this->socket;
    }

    public function write(string|array $data): int
    {
        $packet = is_array($data) ? implode('', $data) : $data;
        self::$writes[] = $packet;

        return strlen($packet);
    }

    public function close(): void
    {
        if (is_resource($this->socket ?? null)) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    public function getResponses(array $sockets, int $timeout, int $stream_timeout): array
    {
        $response = array_shift(self::$roundResponses);
        $socketId = array_key_first($sockets);

        if ($response === null || $socketId === null) {
            return [];
        }

        return [$socketId => $response];
    }
}
