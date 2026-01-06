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

use GameQ\Buffer;
use GameQ\Exception\ProtocolException;
use GameQ\Protocol;
use GameQ\Result;
use GameQ\Server;

/**
 * GTA Five M Protocol Class
 *
 * Server base can be found at https://fivem.net/
 *
 * Based on code found at https://github.com/LiquidObsidian/fivereborn-query
 *
 * @author Austin Bischoff <austin@codebeard.com>
 *
 * Adding FiveM Player List by
 * @author Jesse Lukas <eranio@g-one.org>
 */
class Cfx extends Protocol
{
    /**
     * Array of packets we want to look up.
     * Each key should correspond to a defined method in this or a parent class
     */
    protected array $packets = [
        self::PACKET_STATUS => "\xFF\xFF\xFF\xFFgetinfo xxx",
    ];

    /**
     * Use the response flag to figure out what method to run
     *
     */
    protected array $responses = [
        "\xFF\xFF\xFF\xFFinfoResponse" => "processStatus",
    ];

    /**
     * The query protocol used to make the call
     */
    protected string $protocol = 'cfx';

    /**
     * String name of this protocol class
     */
    protected string $name = 'cfx';

    /**
     * Longer string name of this protocol class
     */
    protected string $name_long = "CitizenFX";

    /**
     * Holds the player list so we can overwrite it back
     */
    protected array $playerList = [];

    /**
     * Normalize settings for this protocol
     */
    protected array $normalize = [
        // General
        'general' => [
            // target       => source
            'gametype'   => 'gametype',
            'hostname'   => 'hostname',
            'mapname'    => 'mapname',
            'maxplayers' => 'sv_maxclients',
            'mod'        => 'gamename',
            'numplayers' => 'clients',
            'password'   => 'privateClients',
        ],
    ];

    /**
     * Get FiveM players list using a sub query
     *
     * @throws \Exception
     */
    public function beforeSend(Server $server): void
    {
        $GameQ = new \GameQ\GameQ();
        $GameQ->addServer([
            'type' => 'cfxplayers',
            'host' => "$server->ip:$server->port_query",
        ]);
        $results = $GameQ->process();
        $this->playerList = $results[0][0] ?? [];
    }

    /**
     * Process the response
     *
     * @return mixed
     * @throws ProtocolException
     */
    public function processResponse(): mixed
    {
        // In case it comes back as multiple packets (it shouldn't)
        $buffer = new Buffer(implode('', $this->packets_response));

        // Figure out what packet response this is for
        $response_type = $buffer->readString(PHP_EOL);

        // Figure out which packet response this is
        if (empty($response_type) || !array_key_exists($response_type, $this->responses)) {
            throw new ProtocolException(__METHOD__ . " response type '$response_type' is not valid");
        }

        // Offload the call
        return $this->{$this->responses[$response_type]}($buffer);
    }

    // Internal methods

    /**
     * Handle processing the status response
     *
     * @return array
     */
    protected function processStatus(Buffer $buffer)
    {
        // Set the result to a new result instance
        $result = new Result();

        // Lets peek and see if the data starts with a \
        if ($buffer->lookAhead() === '\\') {
            // Burn the first one
            $buffer->skip();
        }

        // Explode the data
        $data = explode('\\', $buffer->getBuffer());

        $itemCount = count($data);

        // Now lets loop the array
        for ($x = 0; $x < $itemCount; $x += 2) {
            // Set some local vars
            $key = $data[$x];
            $val = $data[$x + 1];

            if ($key === 'challenge') {
                continue; // skip
            }

            // Regular variable so just add the value.
            $result->add($key, $val);
        }

        // Add result of sub http-protocol if available
        if ($this->playerList) {
            $result->add('players', $this->playerList);
        }

        return $result->fetch();
    }
}
