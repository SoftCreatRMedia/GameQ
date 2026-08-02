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
 * Grand Theft Auto Rage Protocol Class
 * https://rage.mp/masterlist/
 *
 * Result from this call should be a header + JSON response
 *
 * @author K700 <admin@fianna.ru>
 * @author Austin Bischoff <austin@codebeard.com>
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Gtar extends Http
{
    /**
     * Packets to send
     *
     * @var array<string, string>
     */
    protected array $packets = [
        self::PACKET_STATUS => "GET /master/ HTTP/1.0\r\nHost: cdn.rage.mp\r\nAccept: */*\r\n\r\n",
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
    protected string $protocol = 'gtar';

    /**
     * String name of this protocol class
     *
     */
    protected string $name = 'gtar';

    /**
     * Longer string name of this protocol class
     *
     */
    protected string $name_long = "Grand Theft Auto Rage";

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
            'mod'        => 'mod',
            'maxplayers' => 'maxplayers',
            'numplayers' => 'numplayers',
        ],
    ];

    public function beforeSend(Server $server): void
    {
        $this->realIp = $server->ip();
        $this->realPortQuery = $server->portQuery();

        // Override the existing settings
        $server->ip = 'cdn.rage.mp';
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

        $response = $this->extractHttpBody(implode('', $this->packets_response), 'RageMP');

        try {
            $json = json_decode(trim($response), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ProtocolException('JSON response from Gtar protocol is invalid.', 0, $exception);
        }

        if ($this->realIp === null || $this->realPortQuery === null) {
            throw new ProtocolException('The original RageMP server address has not been initialized.');
        }

        $address = $this->realIp . ':' . $this->realPortQuery;
        $server = null;
        $network = null;

        if (is_array($json)) {
            $server = $json[$address] ?? null;

            if (!is_array($server)) {
                foreach ($json as $networkData) {
                    if (!is_array($networkData) || !is_array($networkData['servers'] ?? null)) {
                        continue;
                    }

                    foreach ($networkData['servers'] as $serverData) {
                        if (is_array($serverData) && ($serverData['id'] ?? null) === $address) {
                            $server = $serverData;
                            $network = $networkData;

                            break 2;
                        }
                    }
                }
            }
        }

        if (!is_array($server) || $server === []) {
            return [
                'gq_address'    => $this->realIp,
                'gq_port_query' => $this->realPortQuery,
            ];
        }

        $result = new Result();

        // Server is always dedicated
        $result->add('dedicated', 1);

        $result->add('gq_address', $this->realIp);
        $result->add('gq_port_query', $this->realPortQuery);

        $playerData = is_array($server['players'] ?? null) ? $server['players'] : [];
        $gameMode = $network['gamemode'] ?? $server['gamemode'] ?? null;
        $website = $network['url'] ?? $server['url'] ?? null;
        $language = $network['lang'] ?? $server['lang'] ?? null;
        $numPlayers = $playerData['amount'] ?? $server['players'] ?? null;
        $maxPlayers = $playerData['max'] ?? $server['maxplayers'] ?? null;
        $peak = $playerData['peak'] ?? $server['peak'] ?? null;

        if (is_array($language)) {
            $language = reset($language);
        }

        // Add server items
        $result->add('hostname', $server['name'] ?? null);
        $result->add('mod', $gameMode);
        $result->add('numplayers', $numPlayers);
        $result->add('maxplayers', $maxPlayers);
        $result->add('website', $website);
        $result->add('language', $language);
        $result->add('peak', $peak);
        $result->add('gq_joinlink', 'rage://v/connect/' . $address);
        $result->add('ragemp_name', $server['name'] ?? null);
        $result->add('ragemp_game_mode', $gameMode);
        $result->add('ragemp_website', $website);
        $result->add('ragemp_primary_language', $language);
        $result->add('ragemp_player_peak', $peak);

        return $result->fetch();
    }
}
