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
use JsonException;

/**
 * Tshock Protocol Class
 *
 * Result from this call should be a header + JSON response
 *
 * References:
 * - https://tshock.atlassian.net/wiki/display/TSHOCKPLUGINS/REST+API+Endpoints#RESTAPIEndpoints-/status
 * - http://tshock.co/xf/index.php?threads/rest-tshock-server-status-image.430/
 *
 * Special thanks to intradox and Ruok2bu for game & protocol references
 *
 * @author Austin Bischoff <austin@codebeard.com>
 */
class Tshock extends Http
{
    /**
     * Packets to send
     *
     * @var array<string, string>
     */
    protected array $packets = [
        self::PACKET_STATUS => "GET /v2/server/status?players=true&rules=true HTTP/1.0\r\nAccept: */*\r\n\r\n",
    ];

    /**
     * The protocol being used
     *
     */
    protected string $protocol = 'tshock';

    /**
     * String name of this protocol class
     *
     */
    protected string $name = 'tshock';

    /**
     * Longer string name of this protocol class
     *
     */
    protected string $name_long = "Tshock";

    /**
     * Normalize some items
     */
    protected array $normalize = [
        // General
        'general' => [
            // target       => source
            'dedicated'  => 'dedicated',
            'hostname'   => 'hostname',
            'mapname'    => 'world',
            'maxplayers' => 'maxplayers',
            'numplayers' => 'numplayers',
            'password'   => 'password',
        ],
        // Individual
        'player'  => [
            'name' => 'nickname',
            'team' => 'team',
        ],
    ];

    /**
     * Process the response
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        if ($this->packets_response === []) {
            return [];
        }

        // Implode and rip out the JSON
        preg_match('/[{](.*)[}]/ms', implode('', $this->packets_response), $matches);

        // Return should be JSON, let's validate
        if (!isset($matches[0])) {
            throw new ProtocolException("JSON response from Tshock protocol is invalid.");
        }

        try {
            $json = $this->normalizeStringKeyedArray(json_decode(
                $matches[0],
                true,
                512,
                JSON_THROW_ON_ERROR,
            ));
        } catch (JsonException $exception) {
            throw new ProtocolException("JSON response from Tshock protocol is invalid.", 0, $exception);
        }

        // Check the status response
        $status = $json['status'] ?? null;

        if ($status !== '200' && $status !== 200) {
            throw new ProtocolException(sprintf(
                "JSON status from Tshock protocol response was '%s', expected '200'.",
                is_scalar($status) ? (string) $status : get_debug_type($status),
            ));
        }

        $result = new Result();

        // Server is always dedicated
        $result->add('dedicated', 1);

        // Add server items
        $result->add('hostname', $json['name'] ?? '');
        $result->add('game_port', $json['port'] ?? 0);
        $result->add('serverversion', $json['serverversion'] ?? '');
        $result->add('world', $json['world'] ?? '');
        $result->add('uptime', $json['uptime'] ?? 0);
        $result->add('password', $this->normalizeInteger($json['serverpassword'] ?? 0));
        $result->add('numplayers', $json['playercount'] ?? 0);
        $result->add('maxplayers', $json['maxplayers'] ?? 0);

        // Parse players
        $players = $json['players'] ?? [];

        if (!is_array($players)) {
            throw new ProtocolException("JSON players from Tshock protocol response are invalid.");
        }

        foreach ($players as $playerData) {
            $player = $this->normalizeStringKeyedArray($playerData);

            if ($player === []) {
                continue;
            }

            $result->addPlayer('nickname', $player['nickname'] ?? '');
            $result->addPlayer('username', $player['username'] ?? '');
            $result->addPlayer('group', $player['group'] ?? '');
            $result->addPlayer('active', $this->normalizeInteger($player['active'] ?? 0));
            $result->addPlayer('state', $player['state'] ?? '');
            $result->addPlayer('team', $player['team'] ?? '');
        }

        // Make rules into simple array
        $rules = [];

        // Parse rules
        $responseRules = $json['rules'] ?? [];

        if (!is_array($responseRules)) {
            throw new ProtocolException("JSON rules from Tshock protocol response are invalid.");
        }

        foreach ($responseRules as $rule => $value) {
            if (!is_string($rule)) {
                continue;
            }

            // Add rule but convert boolean into int (0|1)
            $rules[$rule] = (is_bool($value)) ? (int) $value : $value;
        }

        // Add rules
        $result->add('rules', $rules);

        unset($rules, $rule, $player, $value);

        return $result->fetch();
    }
}
