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
use GameQ\Protocol;
use GameQ\Buffer;
use GameQ\Result;

/**
 * Doom3 Protocol Class
 *
 * Handles processing DOOM 3 servers
 *
 * @package GameQ\Protocols
 * @author Wilson Jesus <>
 */
class Doom3 extends Protocol
{
    /**
     * Array of packets we want to look up.
     * Each key should correspond to a defined method in this or a parent class
     */
    protected array $packets = [
        self::PACKET_ALL => "\xFF\xFFgetInfo\x00PiNGPoNG\x00",
    ];

    /**
     * Use the response flag to figure out what method to run
     *
     */
    protected array $responses = [
        "\xFF\xFFinfoResponse" => 'processStatus',
    ];

    /**
     * The query protocol used to make the call
     */
    protected string $protocol = 'doom3';

    /**
     * String name of this protocol class
     */
    protected string $name = 'doom3';

    /**
     * Longer string name of this protocol class
     */
    protected string $name_long = "Doom 3";

    /**
     * The client join link
     */
    protected ?string $join_link = null;

    /**
     * Normalize settings for this protocol
     */
    protected array $normalize = [
        // General
        'general' => [
            // target       => source
            'hostname'   => 'si_name',
            'gametype'   => 'gamename',
            'mapname'    => 'si_map',
            'maxplayers' => 'si_maxPlayers',
            'numplayers' => 'clients',
            'password'   => 'si_usepass',
        ],
        // Individual
        'player'  => [
            'name'  => 'name',
            'ping'  => 'ping',
        ],
    ];

    /**
     * Handle response from the server
     *
     * @return mixed
     * @throws ProtocolException
     */
    public function processResponse(): mixed
    {
        // Make a buffer
        $buffer = new Buffer(implode('', $this->packets_response));

        // Grab the header
        $header = $buffer->readString();

        // Header
        // Figure out which packet response this is
        if (empty($header) || !array_key_exists($header, $this->responses)) {
            throw new ProtocolException(__METHOD__ . " response type '" . bin2hex($header) . "' is not valid");
        }

        return $this->{$this->responses[$header]}($buffer);
    }

    /**
     * Process the status response
     *
     * @return array
     * @throws ProtocolException
     */
    protected function processStatus(Buffer $buffer)
    {
        // We need to split the data and offload
        $results = $this->processServerInfo($buffer);

        return array_merge_recursive(
            $results,
            $this->processPlayers($buffer)
        );
    }

    /**
     * Handle processing the server information
     *
     * @return array
     * @throws ProtocolException
     */
    protected function processServerInfo(Buffer $buffer)
    {
        // Set the result to a new result instance
        $result = new Result();

        $result->add('version', $buffer->readInt8() . '.' . $buffer->readInt8());

        // Key / value pairs, delimited by an empty pair
        while ($buffer->getLength()) {
            $key = trim($buffer->readString());
            $val = Str::isoToUtf8(trim($buffer->readString()));

            // Something is empty so we are done
            if (empty($key) && empty($val)) {
                break;
            }

            $result->add($key, $val);
        }

        return $result->fetch();
    }

    /**
     * Handle processing of player data
     *
     * @return array
     * @throws ProtocolException
     */
    protected function processPlayers(Buffer $buffer)
    {
        // Some games do not have a number of current players
        $playerCount = 0;

        // Set the result to a new result instance
        $result = new Result();

        // Parse players
        // Loop through the buffer until we run out of data
        while (($id = $buffer->readInt8()) !== 32) {
            // Add player info results
            $result->addPlayer('id', $id);
            $result->addPlayer('ping', $buffer->readInt16());
            $result->addPlayer('rate', $buffer->readInt32());
            // Add player name, encoded
            $result->addPlayer('name', Str::isoToUtf8(trim($buffer->readString())));

            // Increment
            $playerCount++;
        }

        // Add the number of players to the result
        $result->add('clients', $playerCount);

        // Clear
        unset($playerCount);

        return $result->fetch();
    }
}
