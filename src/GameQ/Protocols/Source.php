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
 * Valve Source Engine Protocol Class (A2S)
 *
 * This class is used as the basis for all other source based servers
 * that rely on the source protocol for game querying.
 *
 * @author Austin Bischoff <austin@codebeard.com>
 */
class Source extends Protocol
{
    private const CONNECTIONLESS_HEADER = "\xFF\xFF\xFF\xFF";

    private const INFO_REQUEST = self::CONNECTIONLESS_HEADER . "\x54Source Engine Query\x00";

    private const PLAYER_REQUEST = self::CONNECTIONLESS_HEADER . "\x55";

    private const RULES_REQUEST = self::CONNECTIONLESS_HEADER . "\x56";

    private const INITIAL_CHALLENGE = "\xFF\xFF\xFF\xFF";

    private const MAX_DECOMPRESSED_RESPONSE_LENGTH = 16 * 1024 * 1024;

    private const STAGE_INFO = 0;

    private const STAGE_PLAYERS = 1;

    private const STAGE_RULES = 2;

    private const STAGE_COMPLETE = 3;

    /*
     * Source engine type constants
     */
    public const SOURCE_ENGINE = 0;
    public const GOLDSOURCE_ENGINE = 1;

    /**
     * Array of packets we want to look up.
     * Each key should correspond to a defined method in this or a parent class
     */
    protected array $packets = [
        self::PACKET_DETAILS => self::INFO_REQUEST,
        self::PACKET_PLAYERS => self::PLAYER_REQUEST . self::INITIAL_CHALLENGE,
        self::PACKET_RULES => self::RULES_REQUEST . self::INITIAL_CHALLENGE,
    ];

    /**
     * Use the response flag to figure out what method to run
     *
     */
    protected array $responses = [
        "\x49" => "processDetails", // I
        "\x6d" => "processDetailsGoldSource", // m, goldsource
        "\x44" => "processPlayers", // D
        "\x45" => "processRules", // E
    ];

    /**
     * The query protocol used to make the call
     */
    protected string $protocol = 'source';

    /**
     * String name of this protocol class
     */
    protected string $name = 'source';

    /**
     * Longer string name of this protocol class
     */
    protected string $name_long = "Source Server";

    /**
     * Define the Source engine type.  By default it is assumed to be Source
     */
    protected int $source_engine = self::SOURCE_ENGINE;

    /**
     * The client join link
     */
    protected ?string $join_link = "steam://connect/%s:%d/";

    /**
     * Normalize settings for this protocol
     */
    protected array $normalize = [
        // General
        'general' => [
            // target       => source
            'dedicated'  => 'dedicated',
            'gametype'   => 'game_descr',
            'hostname'   => 'hostname',
            'mapname'    => 'map',
            'maxplayers' => 'max_players',
            'mod'        => 'game_dir',
            'numplayers' => 'num_players',
            'password'   => 'password',
        ],
        // Individual
        'player'  => [
            'name'  => 'name',
            'score' => 'score',
            'time'  => 'time',
        ],
    ];

    private int $queryStage = self::STAGE_INFO;

    private int $processedResponseCount = 0;

    private ?string $challenge = null;

    private bool $skipSplitPacketSize = false;

    /**
     * Reset the response-driven A2S query sequence.
     */
    public function beforeSend(Server $server): void
    {
        if ($this->protocol !== 'source') {
            return;
        }

        $this->queryStage = self::STAGE_INFO;
        $this->processedResponseCount = 0;
        $this->challenge = null;
        $this->skipSplitPacketSize = false;
        $this->source_engine = self::SOURCE_ENGINE;
        $this->packets_response = [];
        $this->packets = [
            self::PACKET_DETAILS => self::INFO_REQUEST,
            self::PACKET_PLAYERS => self::PLAYER_REQUEST . self::INITIAL_CHALLENGE,
            self::PACKET_RULES => self::RULES_REQUEST . self::INITIAL_CHALLENGE,
        ];
    }

    /**
     * Begin with an unchanged A2S_INFO request. Player and rules queries are sent as follow-ups.
     *
     * @param list<string>|string $type
     * @return array<string, string>|string
     * @throws ProtocolException
     */
    public function getPacket(array|string $type = []): array|string
    {
        if ($type === '!challenge' && $this->protocol === 'source') {
            return [self::PACKET_DETAILS => $this->packets[self::PACKET_DETAILS]];
        }

        return parent::getPacket($type);
    }

    /**
     * Parse the challenge response and apply it to all the packet types
     *
     * @throws ProtocolException
     */
    public function challengeParseAndApply(Buffer $challenge_buffer): bool
    {
        // Skip the header
        $challenge_buffer->skip(4);

        if ($challenge_buffer->read() !== "\x41") {
            // Response is not a challenge response
            return true;
        }

        $challenge = $challenge_buffer->read(4);
        $this->challenge = $challenge;
        $this->packets[self::PACKET_DETAILS] = self::INFO_REQUEST . $challenge;
        $this->packets[self::PACKET_PLAYERS] = self::PLAYER_REQUEST . $challenge;
        $this->packets[self::PACKET_RULES] = self::RULES_REQUEST . $challenge;

        return true;
    }

    /**
     * Continue the A2S_INFO, A2S_PLAYER, and A2S_RULES sequence one response at a time.
     *
     * @return list<string>
     * @throws ProtocolException
     */
    public function getFollowUpPackets(): array
    {
        if ($this->protocol !== 'source') {
            return [];
        }

        $responseCount = count($this->packets_response);

        if ($this->queryStage === self::STAGE_COMPLETE || $responseCount <= $this->processedResponseCount) {
            return [];
        }

        $responses = array_slice($this->packets_response, $this->processedResponseCount);
        $this->processedResponseCount = $responseCount;
        $challenge = null;
        $hasDataResponse = false;

        foreach ($responses as $response) {
            if (str_starts_with($response, self::CONNECTIONLESS_HEADER . "\x41")) {
                if (strlen($response) !== 9) {
                    throw new ProtocolException('Source challenge response must contain exactly four challenge bytes.');
                }

                $challenge = substr($response, 5, 4);

                continue;
            }

            $hasDataResponse = true;
        }

        if ($challenge !== null) {
            $this->challenge = $challenge;
        }

        if ($hasDataResponse) {
            return $this->advanceQueryStage();
        }

        if ($challenge !== null) {
            return [$this->getCurrentStagePacket($challenge)];
        }

        throw new ProtocolException('Source query entered an invalid follow-up stage.');
    }

    /**
     * Process the response
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        // Will hold the results when complete
        $resultSets = [];

        // Holds sorted response packets
        $packets = [];

        // We need to pre-sort these for split packets so we can do extra work where needed
        foreach ($this->packets_response as $response) {
            $buffer = new Buffer($response);

            // Get the header of packet (long)
            $header = $buffer->readInt32Signed();

            // Single packet
            if ($header === -1) {
                if ($buffer->lookAhead() === "\x41") {
                    if ($buffer->getLength() !== 5) {
                        throw new ProtocolException(
                            'Source challenge response must contain exactly four challenge bytes.',
                        );
                    }

                    continue;
                }

                // We need to peek and see what kind of engine this is for later processing
                if ($buffer->lookAhead() === "\x6d") {
                    $this->source_engine = self::GOLDSOURCE_ENGINE;
                }

                $packets[] = $buffer->getBuffer();
            } elseif ($header === -2) {
                // Split packet

                // Packet Id (long)
                $packet_id = $buffer->readInt32Signed();
                $packetKey = 'split:' . $packet_id;

                // Add the buffer to the packet as another array
                $splitPacket = $packets[$packetKey] ?? [];

                if (!is_array($splitPacket)) {
                    throw new ProtocolException("Packet ID '$packet_id' contains conflicting packet data.");
                }
                $splitPacket[] = $buffer->getBuffer();
                $packets[$packetKey] = $splitPacket;
            } else {
                throw new ProtocolException("Source response header '$header' is not valid.");
            }
        }

        // Free up memory
        unset($packet_id, $buffer, $header);

        // Now that we have the packets sorted we need to iterate and process them
        foreach ($packets as $packet_id => $packet) {
            // We first need to off load split packets to combine them
            if (is_array($packet)) {
                if (!is_string($packet_id) || !str_starts_with($packet_id, 'split:')) {
                    throw new ProtocolException('Split response packet has an invalid packet ID.');
                }

                $buffer = new Buffer($this->processPackets((int) substr($packet_id, 6), $packet));
            } else {
                $buffer = new Buffer($packet);
            }

            // Figure out what packet response this is for
            $response_type = $buffer->read();

            // Figure out which packet response this is
            if (!array_key_exists($response_type, $this->responses)) {
                throw new ProtocolException(__METHOD__ . " response type '$response_type' is not valid");
            }

            // Now we need to call the proper method
            $resultSets[] = $this->processResponseMethod($this->responses[$response_type], $buffer);

            unset($buffer);
        }

        // Free up memory
        unset($packets, $packet_id, $response_type);

        return $resultSets === [] ? [] : array_merge(...$resultSets);
    }

    /*
     * Internal methods
     */

    /**
     * Process the split packets and decompress if necessary
     *
     * @param int $packet_id
     * @param list<string> $packets
     *
     * @return string
     * @throws ProtocolException
     */
    protected function processPackets(int $packet_id, array $packets = []): string
    {
        $packs = [];
        $expectedPacketCount = null;
        $decompressedLength = null;
        $decompressedChecksum = null;
        $compressed = ($packet_id & 0x80000000) !== 0;

        foreach ($packets as $packet) {
            $buffer = new Buffer($packet);

            if ($this->source_engine === self::GOLDSOURCE_ENGINE) {
                $packetCountAndNumber = $buffer->readInt8();
                $packetCount = $packetCountAndNumber & 0x0F;
                $packet_number = ($packetCountAndNumber & 0xF0) >> 4;

                if ($packetCount < 1) {
                    throw new ProtocolException('GoldSource split response has an invalid packet count.');
                }

                if ($expectedPacketCount !== null && $packetCount !== $expectedPacketCount) {
                    throw new ProtocolException('GoldSource split response packets disagree on the packet count.');
                }

                $expectedPacketCount = $packetCount;

                if ($packet_number >= $packetCount || array_key_exists($packet_number, $packs)) {
                    throw new ProtocolException(
                        'GoldSource split response contains an invalid or duplicate packet number.',
                    );
                }

                $packs[$packet_number] = $buffer->getBuffer();

                continue;
            }

            $packetCount = $buffer->readInt8();
            $packet_number = $buffer->readInt8();

            if ($packetCount < 1) {
                throw new ProtocolException('Split response has an invalid packet count.');
            }

            if ($expectedPacketCount !== null && $packetCount !== $expectedPacketCount) {
                throw new ProtocolException('Split response packets disagree on the packet count.');
            }

            $expectedPacketCount = $packetCount;

            if ($packet_number >= $packetCount || array_key_exists($packet_number, $packs)) {
                throw new ProtocolException('Split response contains an invalid or duplicate packet number.');
            }

            $packs[$packet_number] = $buffer->getBuffer();
        }

        ksort($packs);

        if ($expectedPacketCount !== null && count($packs) !== $expectedPacketCount) {
            throw new ProtocolException('Split response is missing one or more packets.');
        }

        if ($this->source_engine !== self::GOLDSOURCE_ENGINE) {
            $hasSplitPacketSize = !$this->skipSplitPacketSize;

            if ($compressed) {
                $firstPacket = $packs[0] ?? null;

                if (!is_string($firstPacket)) {
                    throw new ProtocolException('Compressed split response is missing its first packet.');
                }

                if (str_starts_with(substr($firstPacket, 10), 'BZh')) {
                    $hasSplitPacketSize = true;
                } elseif (str_starts_with(substr($firstPacket, 8), 'BZh')) {
                    // Some older Source servers, including Fortress Forever, omit this field.
                    $hasSplitPacketSize = false;
                } else {
                    throw new ProtocolException('Compressed split response has an invalid bzip2 header.');
                }
            }

            foreach ($packs as $packetNumber => $packet) {
                $buffer = new Buffer($packet);

                if ($hasSplitPacketSize) {
                    $buffer->readInt16();
                }

                if ($compressed && $packetNumber === 0) {
                    $decompressedLength = $buffer->readInt32();
                    $decompressedChecksum = $buffer->readInt32();
                }

                $packs[$packetNumber] = $buffer->getBuffer();
            }
        }

        $result = implode('', $packs);

        if ($compressed) {
            if ($decompressedLength === null || $decompressedChecksum === null) {
                throw new ProtocolException('Compressed split response is missing its compression metadata.');
            }

            if ($decompressedLength > self::MAX_DECOMPRESSED_RESPONSE_LENGTH) {
                throw new ProtocolException('Compressed split response exceeds the decompressed size limit.');
            }

            if (!function_exists('bzopen')) {
                throw new ProtocolException(
                    'Bzip2 is not installed. See https://www.php.net/manual/en/book.bzip2.php for more info.',
                    0,
                );
            }

            $decompressed = $this->decompressSplitResponse($result, $decompressedLength);

            if (strlen($decompressed) !== $decompressedLength) {
                throw new ProtocolException('Decompressed split response has an invalid length.');
            }

            if ((int) sprintf('%u', crc32($decompressed)) !== $decompressedChecksum) {
                throw new ProtocolException('Decompressed split response has an invalid checksum.');
            }

            $result = $decompressed;
        }

        if (!str_starts_with($result, "\xFF\xFF\xFF\xFF")) {
            throw new ProtocolException('Split response is missing its packet header.');
        }

        return substr($result, 4);
    }

    /**
     * Decompress a split response without allowing its output to exceed the declared length.
     *
     * @throws ProtocolException
     */
    private function decompressSplitResponse(string $compressed, int $expectedLength): string
    {
        if (strlen($compressed) > self::MAX_DECOMPRESSED_RESPONSE_LENGTH) {
            throw new ProtocolException('Compressed split response exceeds the compressed size limit.');
        }

        $compressedFile = tempnam(sys_get_temp_dir(), 'gameq-bz2-');

        if ($compressedFile === false) {
            throw new ProtocolException('Unable to allocate a temporary file for the compressed split response.');
        }

        $bzipStream = false;

        try {
            if (file_put_contents($compressedFile, $compressed, LOCK_EX) !== strlen($compressed)) {
                throw new ProtocolException('Unable to buffer the compressed split response.');
            }

            $bzipStream = @bzopen($compressedFile, 'r');

            if (!is_resource($bzipStream)) {
                throw new ProtocolException('Unable to decompress the split response packet.');
            }

            $decompressedChunks = [];
            $decompressedLength = 0;

            while ($decompressedLength <= $expectedLength) {
                $remaining = ($expectedLength + 1) - $decompressedLength;
                $chunk = @bzread($bzipStream, min(8192, $remaining));

                if (!is_string($chunk)) {
                    throw new ProtocolException('Unable to decompress the split response packet.');
                }

                if ($chunk === '') {
                    break;
                }

                $decompressedChunks[] = $chunk;
                $decompressedLength += strlen($chunk);
            }

            if ($decompressedLength > $expectedLength) {
                throw new ProtocolException('Decompressed split response exceeds its declared length.');
            }

            return implode('', $decompressedChunks);
        } finally {
            if (is_resource($bzipStream)) {
                bzclose($bzipStream);
            }

            @unlink($compressedFile);
        }
    }

    /**
     * Handles processing the details data into a usable format
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    protected function processDetails(Buffer $buffer): array
    {
        // Set the result to a new result instance
        $result = new Result();

        $result->add('protocol', $buffer->readInt8());
        $result->add('hostname', $buffer->readString());
        $result->add('map', $buffer->readString());
        $result->add('game_dir', $buffer->readString());
        $result->add('game_descr', $buffer->readString());
        $result->add('steamappid', $buffer->readInt16());
        $result->add('num_players', $buffer->readInt8());
        $result->add('max_players', $buffer->readInt8());
        $result->add('num_bots', $buffer->readInt8());
        $result->add('dedicated', $buffer->read());
        $result->add('os', $buffer->read());
        $result->add('password', $buffer->readInt8());
        $result->add('secure', $buffer->readInt8());

        $this->skipSplitPacketSize = $result->get('protocol') === 7
            && in_array($result->get('steamappid'), [215, 240, 17550, 17700], true);

        // Special result for The Ship only (appid=2400)
        if ($result->get('steamappid') === 2400) {
            $result->add('game_mode', $buffer->readInt8());
            $result->add('witness_count', $buffer->readInt8());
            $result->add('witness_time', $buffer->readInt8());
        }

        $result->add('version', $buffer->readString());

        // Because of php 5.4...
        $edfCheck = $buffer->lookAhead();

        // Extra data flag
        if ($edfCheck !== '') {
            $edf = $buffer->readInt8();

            if (($edf & 0x80) !== 0) {
                $result->add('port', $buffer->readInt16Signed());
            }

            if (($edf & 0x10) !== 0) {
                $result->add('steam_id', $buffer->readInt64());
            }

            if (($edf & 0x40) !== 0) {
                $result->add('sourcetv_port', $buffer->readInt16Signed());
                $result->add('sourcetv_name', $buffer->readString());
            }

            if (($edf & 0x20) !== 0) {
                $result->add('keywords', $buffer->readString());
            }

            if (($edf & 0x01) !== 0) {
                $result->add('game_id', $buffer->readInt64());
            }

            unset($edf);
        }

        return $result->fetch();
    }

    /**
     * Handles processing the server details from goldsource response
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    protected function processDetailsGoldSource(Buffer $buffer): array
    {
        // Set the result to a new result instance
        $result = new Result();

        $result->add('address', $buffer->readString());
        $result->add('hostname', $buffer->readString());
        $result->add('map', $buffer->readString());
        $result->add('game_dir', $buffer->readString());
        $result->add('game_descr', $buffer->readString());
        $result->add('num_players', $buffer->readInt8());
        $result->add('max_players', $buffer->readInt8());
        $result->add('version', $buffer->readInt8());
        $result->add('dedicated', $buffer->read());
        $result->add('os', $buffer->read());
        $result->add('password', $buffer->readInt8());

        // Mod section
        $result->add('ismod', $buffer->readInt8());

        // We only run these if ismod is 1 (true)
        if ($result->get('ismod') === 1) {
            $result->add('mod_urlinfo', $buffer->readString());
            $result->add('mod_urldl', $buffer->readString());
            $buffer->skip();
            $result->add('mod_version', $buffer->readInt32Signed());
            $result->add('mod_size', $buffer->readInt32Signed());
            $result->add('mod_type', $buffer->readInt8());
            $result->add('mod_cldll', $buffer->readInt8());
        }

        $result->add('secure', $buffer->readInt8());
        $result->add('num_bots', $buffer->readInt8());

        return $result->fetch();
    }

    /**
     * Handles processing the player data into a usable format
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    protected function processPlayers(Buffer $buffer): array
    {
        // Set the result to a new result instance
        $result = new Result();

        // Pull out the number of players
        $num_players = $buffer->readInt8();

        // Player count
        $result->add('num_players', $num_players);

        // No players so no need to look any further
        if ($num_players === 0) {
            return $result->fetch();
        }

        // Players list
        while ($buffer->getLength()) {
            $result->addPlayer('id', $buffer->readInt8());
            $result->addPlayer('name', $buffer->readString());
            $result->addPlayer('score', $buffer->readInt32Signed());
            $result->addPlayer('time', $buffer->readFloat32());
        }

        return $result->fetch();
    }

    /**
     * Handles processing the rules data into a usable format
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    protected function processRules(Buffer $buffer): array
    {
        // Set the result to a new result instance
        $result = new Result();

        // Count the number of rules
        $num_rules = $buffer->readInt16Signed();

        // Add the count of the number of rules this server has
        $result->add('num_rules', $num_rules);

        // Rules
        while ($buffer->getLength()) {
            $result->add($buffer->readString(), $buffer->readString());
        }

        return $result->fetch();
    }

    /**
     * Advance the sequential A2S_INFO, A2S_PLAYER, and A2S_RULES query.
     *
     * @return list<string>
     * @throws ProtocolException
     */
    private function advanceQueryStage(): array
    {
        if ($this->queryStage === self::STAGE_INFO) {
            if (($this->options['query_players'] ?? true) !== false) {
                $this->queryStage = self::STAGE_PLAYERS;

                return [$this->getCurrentStagePacket($this->challenge ?? self::INITIAL_CHALLENGE)];
            }

            $this->queryStage = self::STAGE_PLAYERS;
        }

        if ($this->queryStage === self::STAGE_PLAYERS) {
            if (($this->options['query_rules'] ?? true) !== false) {
                $this->queryStage = self::STAGE_RULES;

                return [$this->getCurrentStagePacket($this->challenge ?? self::INITIAL_CHALLENGE)];
            }

            $this->queryStage = self::STAGE_COMPLETE;

            return [];
        }

        if ($this->queryStage === self::STAGE_RULES) {
            $this->queryStage = self::STAGE_COMPLETE;

            return [];
        }

        throw new ProtocolException('Source query cannot advance beyond its complete stage.');
    }

    /**
     * Build the request for the active query stage.
     *
     * @throws ProtocolException
     */
    private function getCurrentStagePacket(string $challenge): string
    {
        return match ($this->queryStage) {
            self::STAGE_INFO => self::INFO_REQUEST . $challenge,
            self::STAGE_PLAYERS => self::PLAYER_REQUEST . $challenge,
            self::STAGE_RULES => self::RULES_REQUEST . $challenge,
            default => throw new ProtocolException('Source query has no packet for its complete stage.'),
        };
    }
}
