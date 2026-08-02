<?php

namespace GameQ\Protocols;

use GameQ\Buffer;
use GameQ\Exception\ProtocolException;
use GameQ\Protocol;
use GameQ\Result;

/**
 * Quake2 Protocol Class
 *
 * Handles processing Quake 3 servers
 *
 * @package GameQ\Protocols
 */
class Quake2 extends Protocol
{
    use QuakeResponseTrait;

    /**
     * Array of packets we want to look up.
     * Each key should correspond to a defined method in this or a parent class
     */
    protected array $packets = [
        self::PACKET_STATUS => "\xFF\xFF\xFF\xFFstatus\x00",
    ];

    /**
     * Use the response flag to figure out what method to run
     *
     */
    protected array $responses = [
        "\xFF\xFF\xFF\xFF\x70\x72\x69\x6e\x74" => 'processStatus',
    ];

    /**
     * The query protocol used to make the call
     */
    protected string $protocol = 'quake2';

    /**
     * String name of this protocol class
     */
    protected string $name = 'quake2';

    /**
     * Longer string name of this protocol class
     */
    protected string $name_long = "Quake 2 Server";

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
            'gametype'   => 'gamename',
            'hostname'   => 'hostname',
            'mapname'    => 'mapname',
            'maxplayers' => 'maxclients',
            'mod'        => 'g_gametype',
            'numplayers' => 'clients',
            'password'   => 'password',
        ],
        // Individual
        'player'  => [
            'name'  => 'name',
            'ping'  => 'ping',
            'score' => 'frags',
        ],
    ];

    /**
     * Handle processing the server information
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    protected function processServerInfo(Buffer $buffer): array
    {
        // Set the result to a new result instance
        $result = new Result();

        // Burn leading \ if one exists
        $buffer->readString('\\');

        // Key / value pairs
        while ($buffer->getLength()) {
            // Add result
            $result->add(
                trim($buffer->readString('\\')),
                $this->convertToUtf8(trim($buffer->readStringMulti(['\\', "\x0a"]))),
            );
        }

        $result->add('password', 0);
        $result->add('mod', 0);

        return $result->fetch();
    }

    /**
     * Handle processing of player data
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    protected function processPlayers(Buffer $buffer): array
    {
        // Some games do not have a number of current players
        $playerCount = 0;

        // Set the result to a new result instance
        $result = new Result();

        // Loop until we are out of data
        while ($buffer->getLength()) {
            // Make a new buffer with this block
            $playerData = $buffer->readString("\x0A");

            if ($playerData === '') {
                continue;
            }

            $playerInfo = new Buffer($playerData);

            // Add player info
            $result->addPlayer('frags', $playerInfo->readString("\x20"));
            $result->addPlayer('ping', $playerInfo->readString("\x20"));

            // Skip first "
            $playerInfo->skip();

            // Add player name, encoded
            $result->addPlayer('name', $this->convertToUtf8(trim(($playerInfo->readString('"')))));

            // Some servers omit the optional quoted address entirely.
            $result->addPlayer('address', trim($playerInfo->getBuffer(), " \t\n\r\0\x0B\""));

            // Increment
            $playerCount++;

            // Clear
            unset($playerInfo);
        }

        $result->add('clients', $playerCount);

        unset($playerCount);

        return $result->fetch();
    }
}
