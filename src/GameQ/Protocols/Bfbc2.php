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

/**
 * Battlefield Bad Company 2 Protocol Class
 *
 * The response packets do not contain request IDs. Their payload structures are used to associate them with the
 * corresponding query instead.
 *
 * @package GameQ\Protocols
 * @author  Austin Bischoff <austin@codebeard.com>
 */
class Bfbc2 extends Protocol
{
    /**
     * Array of packets we want to query.
     */
    protected array $packets = [
        self::PACKET_VERSION => "\x00\x00\x00\x00\x18\x00\x00\x00\x01\x00\x00\x00\x07\x00\x00\x00version\x00",
        self::PACKET_STATUS  => "\x00\x00\x00\x00\x1b\x00\x00\x00\x01\x00\x00\x00\x0a\x00\x00\x00serverInfo\x00",
        self::PACKET_PLAYERS => "\x00\x00\x00\x00\x24\x00\x00\x00\x02\x00\x00\x00"
            . "\x0b\x00\x00\x00listPlayers\x00\x03\x00\x00\x00\x61ll\x00",
    ];

    /**
     * Response processors keyed by payload type
     */
    protected array $responses = [
        'version' => 'processVersion',
        'details' => 'processDetails',
        'players' => 'processPlayers',
    ];

    /**
     * The transport mode for this protocol is TCP
      */
    protected string $transport = self::TRANSPORT_TCP;

    /**
     * The query protocol used to make the call
     */
    protected string $protocol = 'bfbc2';

    /**
     * String name of this protocol class
     */
    protected string $name = 'bfbc2';

    /**
     * Longer string name of this protocol class
     */
    protected string $name_long = "Battlefield Bad Company 2";

    /**
     * The client join link
     */
    protected ?string $join_link = null;

    /**
     * query_port = client_port + 29321
     * 48888 = 19567 + 29321
     */
    protected int $port_diff = 29321;

    /**
     * Normalize settings for this protocol
     */
    protected array $normalize = [
        // General
        'general' => [
            // target       => source
            'dedicated'  => 'dedicated',
            'hostname'   => 'hostname',
            'mapname'    => 'map',
            'maxplayers' => 'max_players',
            'numplayers' => 'num_players',
            'password'   => 'password',
        ],
        'player'  => [
            'name'  => 'name',
            'score' => 'score',
            'ping'  => 'ping',
        ],
        'team'    => [
            'score' => 'tickets',
        ],
    ];

    /**
     * Process the response for the StarMade server
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        // Holds the results sent back
        $resultSets = [];

        // Iterate over the response packets
        foreach ($this->packets_response as $packet) {
            // Create a new buffer
            $buffer = new Buffer($packet);

            // Burn first 4 bytes, same across all packets
            $buffer->skip(4);

            // Get the packet length
            $packetLength = $buffer->getLength();

            // Check to make sure the expected length matches the real length
            // Subtract 4 for the header burn
            if ($packetLength !== ($buffer->readInt32() - 4)) {
                throw new ProtocolException(__METHOD__ . " packet length does not match expected length!");
            }

            $responseType = $this->identifyResponse($this->decode(clone $buffer));
            $resultSets[] = $this->processResponseMethod($this->responses[$responseType], $buffer);
        }

        unset($buffer, $packetLength, $responseType);

        return array_merge(...$resultSets);
    }

    /*
     * Internal Methods
     */

    /**
     * Decode the buffer into a usable format
     *
     * @return list<string>
     * @throws ProtocolException
     */
    protected function decode(Buffer $buffer): array
    {
        $items = [];

        // Get the number of words in this buffer
        $itemCount = $buffer->readInt32();

        // Loop over the number of items
        for ($i = 0; $i < $itemCount; $i++) {
            // Length of the string
            $buffer->readInt32();

            // Just read the string
            $items[] = $buffer->readString();
        }

        return $items;
    }

    /**
     * Identify a response from its decoded payload structure.
     *
     * @param list<string> $items
     * @return 'details'|'players'|'version'
     * @throws ProtocolException
     */
    protected function identifyResponse(array $items): string
    {
        if (($items[0] ?? null) !== 'OK') {
            throw new ProtocolException('BFBC2 response did not indicate success.');
        }

        if (count($items) === 3) {
            return 'version';
        }

        if (isset($items[1]) && ctype_digit($items[1])) {
            $tagCount = (int) $items[1];
            $playerCountIndex = $tagCount + 2;
            $playerCount = $items[$playerCountIndex] ?? null;

            if (
                is_string($playerCount)
                && ctype_digit($playerCount)
                && count($items) === $playerCountIndex + 1 + ($tagCount * (int) $playerCount)
            ) {
                return 'players';
            }
        }

        if (
            isset($items[2], $items[3], $items[6], $items[7], $items[8])
            && ctype_digit($items[2])
            && ctype_digit($items[3])
            && ctype_digit($items[6])
            && ctype_digit($items[7])
            && ctype_digit($items[8])
        ) {
            return 'details';
        }

        throw new ProtocolException('Unable to identify BFBC2 response payload.');
    }

    /**
     * Add the fields shared by Frostbite server-info responses.
     *
     * @param list<string> $items
     */
    protected function populateCommonDetails(array $items, Result $result): int
    {
        $result->add('dedicated', 1);
        $result->add('hostname', $items[1]);
        $result->add('num_players', (int) $items[2]);
        $result->add('max_players', (int) $items[3]);
        $result->add('gametype', $items[4]);
        $result->add('map', $items[5]);
        $result->add('roundsplayed', (int) $items[6]);
        $result->add('roundstotal', (int) $items[7]);
        $result->add('num_teams', (int) $items[8]);

        $indexCurrent = 9;
        $teamCount = (int) $items[8];

        for ($id = 1; $id <= $teamCount; $id++, $indexCurrent++) {
            $result->addTeam('tickets', $items[$indexCurrent]);
            $result->addTeam('id', $id);
        }

        return $indexCurrent;
    }

    /**
     * Process the server details
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    protected function processDetails(Buffer $buffer): array
    {
        // Decode into items
        $items = $this->decode($buffer);

        // Set the result to a new result instance
        $result = new Result();

        $index_current = $this->populateCommonDetails($items, $result);

        // Get and set the rest of the data points.
        $result->add('targetscore', (int) $items[$index_current]);
        $result->add('online', 1); // Forced true, shows accepting players
        $result->add('ranked', (($items[$index_current + 2] === 'true') ? 1 : 0));
        $result->add('punkbuster', (($items[$index_current + 3] === 'true') ? 1 : 0));
        $result->add('password', (($items[$index_current + 4] === 'true') ? 1 : 0));
        $result->add('uptime', (int) $items[$index_current + 5]);
        $result->add('roundtime', (int) $items[$index_current + 6]);
        $result->add('mod', $items[$index_current + 7]);

        $result->add('ip_port', $items[$index_current + 9]);
        $result->add('punkbuster_version', $items[$index_current + 10]);
        $result->add('join_queue', (($items[$index_current + 11] === 'true') ? 1 : 0));
        $result->add('region', $items[$index_current + 12]);

        unset($items, $index_current);

        return $result->fetch();
    }

    /**
     * Process the server version
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    protected function processVersion(Buffer $buffer): array
    {
        // Decode into items
        $items = $this->decode($buffer);

        // Set the result to a new result instance
        $result = new Result();

        $result->add('version', $items[2]);

        unset($items);

        return $result->fetch();
    }

    /**
     * Process the players
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    protected function processPlayers(Buffer $buffer): array
    {
        // Decode into items
        $items = $this->decode($buffer);

        // Set the result to a new result instance
        $result = new Result();

        // Number of data points per player
        $numTags = (int) $items[1];

        // Grab the tags for each player
        $tags = array_slice($items, 2, $numTags);

        // Get the player count
        $playerCount = (int) $items[$numTags + 2];

        // Iterate over the index until we run out of players
        for ($i = 0, $x = $numTags + 3; $i < $playerCount; $i++, $x += $numTags) {
            // Loop over the player tags and extract the info for that tag
            foreach ($tags as $index => $tag) {
                $result->addPlayer($tag, $items[($x + $index)]);
            }
        }

        return $result->fetch();
    }
}
