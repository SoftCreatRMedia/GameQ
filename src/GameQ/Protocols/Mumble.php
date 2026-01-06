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

use GameQ\Protocol;
use GameQ\Result;
use GameQ\Exception\ProtocolException;
use JsonException;

/**
 * Mumble Protocol class
 *
 * References:
 * https://github.com/edmundask/MurmurQuery - Thanks to skylord123
 *
 * @author Austin Bischoff <austin@codebeard.com>
 */
class Mumble extends Protocol
{
    /**
     * Array of packets we want to look up.
     * Each key should correspond to a defined method in this or a parent class
     */
    protected array $packets = [
        self::PACKET_ALL => "\x6A\x73\x6F\x6E", // JSON packet
    ];

    /**
     * The transport mode for this protocol is TCP
      */
    protected string $transport = self::TRANSPORT_TCP;

    /**
     * The query protocol used to make the call
     */
    protected string $protocol = 'mumble';

    /**
     * String name of this protocol class
     */
    protected string $name = 'mumble';

    /**
     * Longer string name of this protocol class
     */
    protected string $name_long = "Mumble Server";

    /**
     * The client join link
     */
    protected ?string $join_link = "mumble://%s:%d/";

    /**
     * 27800 = 64738 - 36938
     */
    protected int $port_diff = -36938;

    /**
     * Normalize settings for this protocol
     */
    protected array $normalize = [
        // General
        'general' => [
            'dedicated'  => 'dedicated',
            'gametype'   => 'gametype',
            'hostname'   => 'name',
            'numplayers' => 'numplayers',
            'maxplayers' => 'x_gtmurmur_max_users',
        ],
        // Player
        'player'  => [
            'name' => 'name',
            'ping' => 'tcpPing',
            'team' => 'channel',
            'time' => 'onlinesecs',
        ],
        // Team
        'team'    => [
            'name' => 'name',
        ],
    ];

    /**
     * Process the response
     *
     * @return mixed
     * @throws ProtocolException|JsonException
     */
    public function processResponse(): mixed
    {
        $data = json_decode(
            implode('', $this->packets_response),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($data)) {
            throw new ProtocolException('Failed to decode JSON response to array');
        }

        // Set the result to a new result instance
        $result = new Result();

        // Always dedicated
        $result->add('dedicated', 1);

        // Let's iterate over the response items, there are a lot
        foreach ($data as $key => $value) {
            // Ignore root for now, that is where all of the channel/player info is housed
            if ($key === 'root') {
                continue;
            }

            // Add them as is
            $result->add($key, $value);
        }

        // Offload the channel and user parsing
        $this->processChannelsAndUsers($data['root'], $result);

        unset($data);

        // Manually set the number of players
        $result->add('numplayers', count($result->get('players')));

        return $result->fetch();
    }

    // Internal methods

    /**
     * Handles processing the the channels and user info
     */
    protected function processChannelsAndUsers(array $data, Result $result): void
    {
        // Let's add all of the channel information
        foreach ($data as $key => $value) {
            // We will handle these later
            if (in_array($key, ['channels', 'users'])) {
                // skip
                continue;
            }

            // Add the channel property as a team
            $result->addTeam($key, $value);
        }

        // Itereate over the users in this channel
        foreach ($data['users'] as $user) {
            foreach ($user as $key => $value) {
                $result->addPlayer($key, $value);
            }
        }

        // Offload more channels to parse
        foreach ($data['channels'] as $channel) {
            $this->processChannelsAndUsers($channel, $result);
        }
    }
}
