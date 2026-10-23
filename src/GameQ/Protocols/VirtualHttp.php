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
 * A directory target that has an identity but no public gameplay IP or port.
 *
 * Queries are performed through the injected HTTP client in beforeSend().
 * Never substitute the directory's address for an unknown game's endpoint.
 */
abstract class VirtualHttp extends Http
{
    abstract public function targetId(): string;

    public function queryHttp(Server $server, int $timeout): void
    {
        // Virtual targets have no address against which packet templates can run.
    }
}
