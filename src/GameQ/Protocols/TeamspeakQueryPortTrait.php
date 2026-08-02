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
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace GameQ\Protocols;

use GameQ\Exception\ProtocolException;
use GameQ\Server;

/**
 * Shared TeamSpeak packet preparation.
 */
trait TeamspeakQueryPortTrait
{
    /**
     * Insert the selected virtual server's client port into every query packet.
     *
     * @throws ProtocolException
     */
    public function beforeSend(Server $server): void
    {
        $queryPort = $this->options[Server::SERVER_OPTIONS_QUERY_PORT] ?? null;

        if ($queryPort === null || $queryPort === '') {
            throw new ProtocolException(
                static::class . "::beforeSend Missing required setting '" . Server::SERVER_OPTIONS_QUERY_PORT . "'.",
            );
        }

        foreach ($this->packets as $packetType => $packet) {
            $this->packets[$packetType] = sprintf($packet, $server->portClient());
        }
    }
}
