<?php

namespace GameQ\Protocols;

use GameQ\Buffer;
use GameQ\Exception\ProtocolException;
use GameQ\Protocol;
use GameQ\Result;

/**
 * Frontlines Fuel of War Protocol Class
 *
 * Handles processing ffow servers
 *
 * Protocol specification:
 * https://web.archive.org/web/20091002183549/http://wiki.hlsw.net/index.php/FFOW_Protocol
 *
 * @package GameQ\Protocols
 */
class Ffow extends Protocol
{
    /**
     * Array of packets we want to look up.
     * Each key should correspond to a defined method in this or a parent class
     */
    protected array $packets = [
        self::PACKET_CHALLENGE => "\xFF\xFF\xFF\xFF\x57",
        self::PACKET_RULES     => "\xFF\xFF\xFF\xFF\x56%s",
        self::PACKET_PLAYERS   => "\xFF\xFF\xFF\xFF\x55%s",
        self::PACKET_INFO      => "\xFF\xFF\xFF\xFF\x46\x4C\x53\x51",
    ];

    /**
     * Use the response flag to figure out what method to run
     *
     */
    protected array $responses = [
        "\xFF\xFF\xFF\xFF\x49" => 'processInfo', // I
        "\xFF\xFF\xFF\xFF\x45" => 'processRules', // E
        "\xFF\xFF\xFF\xFF\x44" => 'processPlayers', // D
    ];

    /**
     * The query protocol used to make the call
     */
    protected string $protocol = 'ffow';

    /**
     * String name of this protocol class
     */
    protected string $name = 'ffow';

    /**
     * Longer string name of this protocol class
     */
    protected string $name_long = "Frontlines Fuel of War";

    /**
     * The client join link
     */
    protected ?string $join_link = null;

    /**
     * query_port = client_port + 2
     */
    protected int $port_diff = 2;

    /**
     * Normalize settings for this protocol
     */
    protected array $normalize = [
        // General
        'general' => [
            // target       => source
            'gametype'   => 'gamemode',
            'hostname'   => 'servername',
            'mapname'    => 'mapname',
            'maxplayers' => 'max_players',
            'mod'        => 'modname',
            'numplayers' => 'num_players',
            'password'   => 'password',
        ],
        // Individual
        'player'  => [
            'name'  => 'name',
            'ping'  => 'ping',
            'score' => 'score',
            'time'  => 'time',
        ],
    ];

    /**
     * Parse the challenge response and apply it to all the packet types
     *
     * @throws ProtocolException
     */
    public function challengeParseAndApply(Buffer $challenge_buffer): bool
    {
        // Burn padding
        $challenge_buffer->skip(5);

        // Apply the challenge and return
        return $this->challengeApply($challenge_buffer->read(4));
    }

    /**
     * Handle response from the server
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        // Init results
        $resultSets = [];

        foreach ($this->reassembleResponses() as $response) {
            $buffer = new Buffer($response, Buffer::NUMBER_TYPE_BIGENDIAN);

            // Figure out what packet response this is for
            $response_type = $buffer->read(5);

            // Figure out which packet response this is
            if (!array_key_exists($response_type, $this->responses)) {
                throw new ProtocolException(
                    __METHOD__ . " response type '" . bin2hex($response_type) . "' is not valid",
                );
            }

            // Now we need to call the proper method
            $resultSets[] = $this->processResponseMethod($this->responses[$response_type], $buffer);

            unset($buffer);
        }

        return array_merge(...$resultSets);
    }

    /**
     * Handle processing the server information
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    protected function processInfo(Buffer $buffer): array
    {
        // Set the result to a new result instance
        $result = new Result();

        // Network protocol version
        $buffer->skip();

        $result->add('servername', $buffer->readString());
        $result->add('mapname', $buffer->readString());
        $result->add('modname', $buffer->readString());
        $result->add('gamemode', $buffer->readString());
        $result->add('description', $buffer->readString());
        $result->add('version', $buffer->readString());
        $result->add('port', $buffer->readInt16());
        $result->add('num_players', $buffer->readInt8());
        $result->add('max_players', $buffer->readInt8());
        $result->add('dedicated', $buffer->read());
        $result->add('os', $buffer->read());
        $result->add('password', $buffer->readInt8());
        $result->add('anticheat', $buffer->readInt8());
        $result->add('average_fps', $buffer->readInt8());
        $result->add('round', $buffer->readInt8());
        $result->add('max_rounds', $buffer->readInt8());
        $result->add('time_left', $buffer->readInt16());

        return $result->fetch();
    }

    /**
     * Handle processing the server rules
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    protected function processRules(Buffer $buffer): array
    {
        // Set the result to a new result instance
        $result = new Result();

        $ruleCount = $buffer->readInt16();

        for ($index = 0; $index < $ruleCount; $index++) {
            $key = $buffer->readString();

            // Check for map
            if (str_contains($key, "Map:")) {
                $result->addSub("maplist", "name", $buffer->readString());
            } else { // Regular rule
                $result->add($key, $buffer->readString());
            }
        }

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
        $result = new Result();
        $playerCount = $buffer->readInt8();

        for ($index = 0; $index < $playerCount; $index++) {
            $result->addPlayer('id', $buffer->readInt8());
            $result->addPlayer('name', $buffer->readString());
            $result->addPlayer('score', $buffer->readInt32Signed());
            $result->addPlayer('time', $buffer->readFloat32());
            $result->addPlayer('ping', $buffer->readInt16());
            $result->addPlayer('profile_id', $buffer->readInt32());
            $result->addPlayer('team', $buffer->readInt8());
        }

        return $result->fetch();
    }

    /**
     * Reassemble FFOW responses that exceed a single UDP packet.
     *
     * @return list<string>
     * @throws ProtocolException
     */
    protected function reassembleResponses(): array
    {
        $responses = [];

        /** @var array<int, array{count: int, fragments: array<int, string>}> $groups */
        $groups = [];

        foreach ($this->packets_response as $response) {
            $buffer = new Buffer($response, Buffer::NUMBER_TYPE_BIGENDIAN);
            $header = $buffer->readInt32Signed();

            if ($header === -1) {
                $responses[] = $response;

                continue;
            }

            if ($header !== -2) {
                throw new ProtocolException('FFOW response has an invalid packet header.');
            }

            $requestId = $buffer->readInt32();
            $packetNumber = $buffer->readInt8();
            $packetCount = $buffer->readInt8();

            // Maximum split size; the final fragment may be smaller
            $buffer->readInt16();

            if ($packetCount === 0 || $packetNumber >= $packetCount) {
                throw new ProtocolException('FFOW split response contains an invalid packet number.');
            }

            if (!isset($groups[$requestId])) {
                $groups[$requestId] = [
                    'count' => $packetCount,
                    'fragments' => [],
                ];
            }

            if ($groups[$requestId]['count'] !== $packetCount) {
                throw new ProtocolException('FFOW split response packets disagree on the packet count.');
            }

            if (isset($groups[$requestId]['fragments'][$packetNumber])) {
                throw new ProtocolException('FFOW split response contains a duplicate packet number.');
            }

            $groups[$requestId]['fragments'][$packetNumber] = $buffer->getBuffer();
        }

        foreach ($groups as $group) {
            if (count($group['fragments']) !== $group['count']) {
                throw new ProtocolException('FFOW split response is missing one or more packets.');
            }

            ksort($group['fragments']);
            $response = implode('', $group['fragments']);

            if (!str_starts_with($response, "\xFF\xFF\xFF\xFF")) {
                throw new ProtocolException('Reassembled FFOW response is missing its packet header.');
            }

            $responses[] = $response;
        }

        return $responses;
    }
}
