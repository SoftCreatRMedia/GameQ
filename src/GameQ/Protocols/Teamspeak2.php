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
 * Teamspeak 2 Protocol Class
 *
 * All values are utf8 encoded upon processing
 *
 * This code ported from GameQ v1/v2. Credit to original author(s) as I just updated it to
 * work within this new system.
 *
 * @author Austin Bischoff <austin@codebeard.com>
 */
class Teamspeak2 extends Protocol
{
    use TeamspeakQueryPortTrait;

    /**
     * Array of packets we want to look up.
     * Each key should correspond to a defined method in this or a parent class
     */
    protected array $packets = [
        self::PACKET_DETAILS  => "sel %d\x0asi\x0a",
        self::PACKET_CHANNELS => "sel %d\x0acl\x0a",
        self::PACKET_PLAYERS  => "sel %d\x0apl\x0a",
    ];

    /**
     * The transport mode for this protocol is TCP
      */
    protected string $transport = self::TRANSPORT_TCP;

    /**
     * The query protocol used to make the call
     */
    protected string $protocol = 'teamspeak2';

    /**
     * String name of this protocol class
     */
    protected string $name = 'teamspeak2';

    /**
     * Longer string name of this protocol class
     */
    protected string $name_long = "Teamspeak 2";

    /**
     * The client join link
     */
    protected ?string $join_link = "teamspeak://%s:%d/";

    /**
     * Normalize settings for this protocol
     */
    protected array $normalize = [
        // General
        'general' => [
            'dedicated'  => 'dedicated',
            'hostname'   => 'server_name',
            'password'   => 'server_password',
            'numplayers' => 'server_currentusers',
            'maxplayers' => 'server_maxusers',
        ],
        // Player
        'player'  => [
            'id'   => 'p_id',
            'team' => 'c_id',
            'name' => 'nick',
        ],
        // Team
        'team'    => [
            'id'   => 'id',
            'name' => 'name',
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
        // Make a new buffer out of all of the packets
        $buffer = new Buffer(implode('', $this->packets_response));

        // Check the header [TS]
        if (($header = trim($buffer->readString("\n"))) !== '[TS]') {
            throw new ProtocolException(__METHOD__ . " Expected header '$header' does not match expected '[TS]'.");
        }

        // Split this buffer as the data blocks are bound by "OK" and drop any empty values
        $sections = array_filter(
            explode("OK", $buffer->getBuffer()),
            static fn(string $value): bool => trim($value) !== '',
        );

        // Trim up the values to remove extra whitespace
        $sections = array_map('trim', $sections);

        // Set the result to a new result instance
        $result = new Result();

        // Now we need to iterate over the sections and off load the processing
        foreach ($sections as $section) {
            // Grab a snip of the data so we can figure out what it is
            $check = substr($section, 0, 7);

            // Offload to the proper method
            if ($check === 'server_') {
                // Server settings and info
                $this->processDetails($section, $result);
            } elseif ($check === "id\tcode") {
                // Channel info
                $this->processChannels($section, $result);
            } elseif ($check === "p_id\tc_") {
                // Player info
                $this->processPlayers($section, $result);
            }
        }

        unset($buffer, $sections, $check);

        return $result->fetch();
    }

    /*
     * Internal methods
     */


    /**
     * Handles processing the details data into a usable format
     *
     * @param string $data
     * @param Result $result
     * @return void
     * @throws ProtocolException
     */
    protected function processDetails(string $data, Result $result): void
    {
        // Create a buffer
        $buffer = new Buffer($data);

        // Always dedicated
        $result->add('dedicated', 1);

        // Let's loop until we run out of data
        while ($buffer->getLength()) {
            // Grab the row, which is an item
            $row = trim($buffer->readString("\n"));

            // Split out the information
            [$key, $value] = explode('=', $row, 2);

            // Add this to the result
            $result->add($key, $this->convertToUtf8($value));
        }

        unset($buffer, $row, $key, $value);
    }

    /**
     * Process the channel listing
     *
     * @param string $data
     * @param Result $result
     * @return void
     * @throws ProtocolException
     */
    protected function processChannels(string $data, Result $result): void
    {
        // Create a buffer
        $buffer = new Buffer($data);

        // The first line holds the column names, data returned is in column/row format
        $columns = explode("\t", trim($buffer->readString("\n")), 9);

        // Loop through the rows until we run out of information
        while ($buffer->getLength()) {
            // Grab the row, which is a tabbed list of items
            $row = trim($buffer->readString("\n"));

            // Explode and merge the data with the columns, then parse
            $values = explode("\t", $row, 9);

            if (count($columns) !== count($values)) {
                throw new ProtocolException('The TeamSpeak 2 channel response has invalid columns.');
            }
            $data = array_combine($columns, $values);

            foreach ($data as $key => $value) {
                // Now add the data to the result
                $result->addTeam($key, $this->convertToUtf8($value));
            }
        }

        unset($buffer, $row, $columns, $key, $value);
    }

    /**
     * Process the user listing
     *
     * @param string $data
     * @param Result $result
     * @return void
     * @throws ProtocolException
     */
    protected function processPlayers(string $data, Result $result): void
    {
        // Create a buffer
        $buffer = new Buffer($data);

        // The first line holds the column names, data returned is in column/row format
        $columns = explode("\t", trim($buffer->readString("\n")), 16);

        // Loop through the rows until we run out of information
        while ($buffer->getLength()) {
            // Grab the row, which is a tabbed list of items
            $row = trim($buffer->readString("\n"));

            // Explode and merge the data with the columns, then parse
            $values = explode("\t", $row, 16);

            if (count($columns) !== count($values)) {
                throw new ProtocolException('The TeamSpeak 2 player response has invalid columns.');
            }
            $data = array_combine($columns, $values);

            foreach ($data as $key => $value) {
                // Now add the data to the result
                $result->addPlayer($key, $this->convertToUtf8($value));
            }
        }

        unset($buffer, $row, $columns, $key, $value);
    }
}
