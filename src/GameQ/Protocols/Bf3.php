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
use GameQ\Result;

/**
 * Battlefield 3 Protocol Class
 *
 * Good place for doc status and info is http://www.fpsadmin.com/forum/showthread.php?t=24134
 *
 * @package GameQ\Protocols
 * @author  Austin Bischoff <austin@codebeard.com>
 */
class Bf3 extends Bfbc2
{
    /**
     * Array of packets we want to query.
     */
    protected array $packets = [
        self::PACKET_STATUS  => "\x00\x00\x00\x21\x1b\x00\x00\x00\x01\x00\x00\x00\x0a\x00\x00\x00serverInfo\x00",
        self::PACKET_VERSION => "\x00\x00\x00\x22\x18\x00\x00\x00\x01\x00\x00\x00\x07\x00\x00\x00version\x00",
        self::PACKET_PLAYERS
            => "\x00\x00\x00\x23\x24\x00\x00\x00\x02\x00\x00\x00\x0b\x00\x00\x00listPlayers\x00\x03\x00\x00\x00\x61ll\x00",
    ];

    /**
     * Use the response flag to figure out what method to run
     *
     */
    protected array $responses = [
        1627389952 => "processDetails", // a
        1644167168 => "processVersion", // b
        1660944384 => "processPlayers", // c
    ];

    /**
     * The transport mode for this protocol is TCP
      */
    protected string $transport = self::TRANSPORT_TCP;

    /**
     * The query protocol used to make the call
     */
    protected string $protocol = 'bf3';

    /**
     * String name of this protocol class
     */
    protected string $name = 'bf3';

    /**
     * Longer string name of this protocol class
     */
    protected string $name_long = "Battlefield 3";

    /**
     * The client join link
     */
    protected ?string $join_link = null;

    /**
     * query_port = client_port + 22000
     * 47200 = 25200 + 22000
     */
    protected int $port_diff = 22000;

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

        // Holds the processed packets after having been reassembled
        $processed = [];

        // Start up the index for the processed
        $sequence_id_last = 0;

        foreach ($this->packets_response as $packet) {
            // Create a new buffer
            $buffer = new Buffer($packet);

            // Each "good" packet begins with sequence_id (32-bit)
            $sequence_id = $buffer->readInt32();

            // Sequence id is a response
            if (array_key_exists($sequence_id, $this->responses)) {
                $processed[$sequence_id] = $buffer->getBuffer();
                $sequence_id_last = $sequence_id;
            } else {
                // This is a continuation of the previous packet, reset the buffer and append
                $buffer->jumpto(0);

                // Append
                $processed[$sequence_id_last] .= $buffer->getBuffer();
            }
        }

        unset($buffer, $sequence_id_last, $sequence_id);

        // Iterate over the combined packets and do some work
        foreach ($processed as $sequence_id => $data) {
            // Create a new buffer
            $buffer = new Buffer($data);

            // Get the length of the packet
            $packetLength = $buffer->getLength();

            // Check to make sure the expected length matches the real length
            // Subtract 4 for the sequence_id pulled out earlier
            if ($packetLength !== ($buffer->readInt32() - 4)) {
                throw new ProtocolException(__METHOD__ . " packet length does not match expected length!");
            }

            // Now we need to call the proper method
            $resultSets[] = $this->processResponseMethod($this->responses[$sequence_id], $buffer);
        }

        return array_merge(...$resultSets);
    }

    /*
     * Internal Methods
     */

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

        $this->populateBattlefield3Details($items, $result, $index_current);

        // Battlefield 3 added the quick-match flag at this position in R29
        $result->add('quickmatch', (int) $items[$index_current + 13]);

        return $result->fetch();
    }

    /**
     * Add the fields shared by Battlefield 3 and 4 server-info responses.
     *
     * @param list<string> $items
     */
    protected function populateBattlefield3Details(array $items, Result $result, int $indexCurrent): void
    {
        $result->add('targetscore', (int) $items[$indexCurrent]);
        $result->add('online', 1); // Forced true, the following response item is always empty
        $result->add('ranked', (int) $items[$indexCurrent + 2]);
        $result->add('punkbuster', (int) $items[$indexCurrent + 3]);
        $result->add('password', (int) $items[$indexCurrent + 4]);
        $result->add('uptime', (int) $items[$indexCurrent + 5]);
        $result->add('roundtime', (int) $items[$indexCurrent + 6]);
        $result->add('ip_port', $items[$indexCurrent + 7]);
        $result->add('punkbuster_version', $items[$indexCurrent + 8]);
        $result->add('join_queue', (int) $items[$indexCurrent + 9]);
        $result->add('region', $items[$indexCurrent + 10]);
        $result->add('pingsite', $items[$indexCurrent + 11]);
        $result->add('country', $items[$indexCurrent + 12]);
    }
}
