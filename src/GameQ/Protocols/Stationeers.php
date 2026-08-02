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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace GameQ\Protocols;

use GameQ\Exception\ProtocolException;
use GameQ\Result;
use GameQ\Server;
use JsonException;

/**
 * Stationeers Protocol Class
 *
 * **Note:** This protocol does use the official, centralized "Metaserver" to query the list of all available servers.
 * This is effectively a host controlled by a third party which could interfere with this protocol.
 *
 * @author Austin Bischoff <austin@codebeard.com>
 */
class Stationeers extends Http
{
    /**
     * The host (address) of the "Metaserver" to query to get the list of servers
     */
    public const SERVER_LIST_HOST = '40.82.200.175';

    /**
     * The port of the "Metaserver" to query to get the list of servers
     */
    public const SERVER_LIST_PORT = 8081;

    /**
     * Packets to send
     *
     * @var array<string, string>
     */
    protected array $packets = [
        self::PACKET_STATUS => "GET /list HTTP/1.0\r\nAccept: */*\r\n\r\n",
    ];

    /**
     * The protocol being used
     *
     * @var string
     */
    protected string $protocol = 'stationeers';

    /**
     * String name of this protocol class
     *
     * @var string
     */
    protected string $name = 'stationeers';

    /**
     * Longer string name of this protocol class
     *
     * @var string
     */
    protected string $name_long = "Stationeers";

    /**
     * Normalize some items
     *
     * @var array<string, array<string, string|list<string>>>
     */
    protected array $normalize = [
        // General
        'general' => [
            // target       => source
            'dedicated'  => 'dedicated',
            'hostname'   => 'hostname',
            'mapname'    => 'map',
            'maxplayers' => 'maxplayers',
            'numplayers' => 'numplayers',
            'password'   => 'password',
        ],
    ];

    /**
     * Holds the real ip so we can overwrite it back
     *
     * **NOTE:** These is used during the runtime.
     *
     * @var string|null
     */
    protected ?string $realIp = null;

    /**
     * Holds the real port so we can overwrite it back
     *
     * **NOTE:** These is used during the runtime.
     *
     * @var int|null
     */
    protected ?int $realPortQuery = null;

    /**
     * Handle changing the call to call a central server rather than the server directly
     *
     * @param Server $server
     *
     * @return void
     */
    public function beforeSend(Server $server): void
    {
        /* Determine the connection information to be used for the "Metaserver" */
        $hostOption = $server->getOption('meta_host');
        $metaServerHost = is_string($hostOption) && $hostOption !== ''
            ? $hostOption
            : self::SERVER_LIST_HOST;
        $metaServerPort = $this->normalizeInteger($server->getOption('meta_port'), self::SERVER_LIST_PORT);

        if ($metaServerPort < 1 || $metaServerPort > 65535) {
            $metaServerPort = self::SERVER_LIST_PORT;
        }

        /* Save the real connection information and point the server at the Metaserver. */
        $this->realIp = $server->ip();
        $this->realPortQuery = $server->portQuery();
        $server->ip = $metaServerHost;
        $server->port_query = $metaServerPort;
    }

    /**
     * Process the response
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        /* Ensure there is a reply from the "Metaserver" */
        if ($this->packets_response === []) {
            return [];
        }

        // Implode and rip out the JSON
        preg_match('/[{](.*)[}]/ms', implode('', $this->packets_response), $matches);

        // Return should be JSON, let's validate
        if (!isset($matches[0])) {
            throw new ProtocolException(__METHOD__ . " JSON response from Stationeers Metaserver is invalid.");
        }

        try {
            $decoded = json_decode($matches[0], true, 512, JSON_THROW_ON_ERROR);
            $json = $this->normalizeStringKeyedArray($decoded);
        } catch (JsonException $exception) {
            throw new ProtocolException(
                __METHOD__ . " JSON response from Stationeers Metaserver is invalid.",
                0,
                $exception,
            );
        }

        if (!isset($json['GameSessions']) || !is_array($json['GameSessions'])) {
            throw new ProtocolException(__METHOD__ . " Stationeers Metaserver response has no server list.");
        }

        // By default no server is found
        $server = null;

        // Find the server on this list by iterating over the entire list.
        foreach ($json['GameSessions'] as $serverData) {
            $serverEntry = $this->normalizeStringKeyedArray($serverData);

            if ($serverEntry === []) {
                continue;
            }

            // Server information passed matches an entry on this list
            $addressMatches = ($serverEntry['Address'] ?? null) === $this->realIp;
            $portMatches = $this->normalizeInteger($serverEntry['Port'] ?? 0) === $this->realPortQuery;

            if ($addressMatches && $portMatches) {
                $server = $serverEntry;

                break;
            }
        }

        /* Send to the garbage collector */
        unset($matches, $serverEntry, $json);

        /* Ensure the provided Server has been found in the list provided by the "Metaserver" */
        if ($server === null || $this->realIp === null || $this->realPortQuery === null) {
            throw new ProtocolException(sprintf(
                '%s Unable to find the server "%s:%d" in the Stationeer Metaservers server list',
                __METHOD__,
                $this->realIp,
                $this->realPortQuery,
            ));
        }

        /* Build the Result from the parsed JSON */
        $result = new Result();
        $result->add('dedicated', 1); // Server is always dedicated
        $result->add('hostname', $server['Name'] ?? '');
        $result->add('gq_address', $server['Address'] ?? '');
        $result->add('gq_port_query', $server['Port'] ?? 0);
        $result->add('version', $server['Version'] ?? '');
        $result->add('map', $server['MapName'] ?? '');
        $result->add('uptime', $server['UpTime'] ?? 0);
        $result->add('password', $this->normalizeInteger($server['Password'] ?? 0));
        $result->add('numplayers', $server['Players'] ?? 0);
        $result->add('maxplayers', $server['MaxPlayers'] ?? 0);
        $result->add('type', $server['Type'] ?? '');

        /* Send to the garbage collector */
        unset($server);

        return $result->fetch();
    }
}
