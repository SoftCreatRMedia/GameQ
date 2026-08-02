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
 * Grand Theft Auto Network Protocol Class
 * https://stats.gtanet.work/
 *
 * Result from this call should be a header + JSON response
 *
 * References:
 * - https://master.gtanet.work/apiservers
 *
 * @author Austin Bischoff <austin@codebeard.com>
 */
class Gtan extends Http
{
    /**
     * Packets to send
     *
     * @var array<string, string>
     */
    protected array $packets = [
        //self::PACKET_STATUS => "GET /apiservers HTTP/1.0\r\nHost: master.gtanet.work\r\nAccept: */*\r\n\r\n",
        self::PACKET_STATUS => "GET /gtan/api.php?ip=%s&raw HTTP/1.0\r\n"
            . "Host: multiplayerhosting.info\r\nAccept: */*\r\n\r\n",
    ];

    /**
     * Http protocol is SSL
     *
     */
    protected string $transport = self::TRANSPORT_SSL;

    /**
     * The protocol being used
     *
     */
    protected string $protocol = 'gtan';

    /**
     * String name of this protocol class
     *
     */
    protected string $name = 'gtan';

    /**
     * Longer string name of this protocol class
     *
     */
    protected string $name_long = "Grand Theft Auto Network";

    /**
     * Holds the real ip so we can overwrite it back
     */
    protected ?string $realIp = null;

    /**
     * Holds the real query port so we can overwrite it back
     */
    protected ?int $realPortQuery = null;

    /**
     * Normalize some items
     */
    protected array $normalize = [
        // General
        'general' => [
            // target       => source
            'dedicated'  => 'dedicated',
            'hostname'   => 'hostname',
            'mapname'    => 'map',
            'mod'        => 'mod',
            'maxplayers' => 'maxplayers',
            'numplayers' => 'numplayers',
            'password'   => 'password',
        ],
    ];

    public function beforeSend(Server $server): void
    {
        // Loop over the packets and update them
        foreach ($this->packets as $packetType => $packet) {
            // Fill out the packet with the server info
            $this->packets[$packetType] = sprintf($packet, $server->ip . ':' . $server->port_query);
        }

        $this->realIp = $server->ip;
        $this->realPortQuery = $server->port_query;

        // Override the existing settings
        //$server->ip = 'master.gtanet.work';
        $server->ip = 'multiplayerhosting.info';
        $server->port_query = 443;
    }

    /**
     * Process the response
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        // No response, assume offline
        if ($this->packets_response === []) {
            return [
                'gq_address'    => $this->realIp,
                'gq_port_query' => $this->realPortQuery,
            ];
        }

        // Implode and rip out the JSON
        preg_match('/[{](.*)[}]/ms', implode('', $this->packets_response), $matches);

        // Return should be JSON, let's validate
        if (!isset($matches[0])) {
            throw new ProtocolException("JSON response from Gtan protocol is invalid.");
        }

        try {
            $json = $this->normalizeStringKeyedArray(json_decode($matches[0], true, 512, JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new ProtocolException("JSON response from Gtan protocol is invalid.", 0, $exception);
        }

        $result = new Result();

        // Server is always dedicated
        $result->add('dedicated', 1);

        $result->add('gq_address', $this->realIp);
        $result->add('gq_port_query', $this->realPortQuery);

        // Add server items
        $result->add('hostname', $json['ServerName'] ?? '');
        $result->add('serverversion', $json['ServerVersion'] ?? '');
        $map = $json['Map'] ?? null;
        $result->add('map', is_string($map) && $map !== '' ? $map : 'Los Santos/Blaine Country');
        $result->add('mod', $json['Gamemode'] ?? '');
        $result->add('password', $this->normalizeInteger($json['Passworded'] ?? 0));
        $result->add('numplayers', $json['CurrentPlayers'] ?? 0);
        $result->add('maxplayers', $json['MaxPlayers'] ?? 0);

        return $result->fetch();
    }
}
