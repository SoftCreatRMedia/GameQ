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

/**
 * In-memory query transport used to verify server batching.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
final class BatchQuery extends Core
{
    /** @var list<int> */
    public static array $socketCounts = [];

    public static function resetMetrics(): void
    {
        self::$socketCounts = [];
    }

    /**
     * @throws RuntimeException
     */
    protected function create(): void
    {
        $socket = fopen('php://memory', 'r+b');

        if ($socket === false) {
            throw new RuntimeException('Unable to create the batch-query test socket.');
        }

        $this->socket = $socket;
    }

    /**
     * @return resource
     * @throws RuntimeException
     */
    public function get(): mixed
    {
        if (!is_resource($this->socket ?? null)) {
            $this->create();
        }

        if (!is_resource($this->socket)) {
            throw new RuntimeException('Unable to retrieve the batch-query test socket.');
        }

        return $this->socket;
    }

    public function write(string|array $data): int
    {
        return strlen(is_array($data) ? implode('', $data) : $data);
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
        self::$socketCounts[] = count($sockets);

        return [];
    }
}
