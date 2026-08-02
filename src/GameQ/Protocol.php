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
 *
 *
 */

namespace GameQ;

use GameQ\Exception\ProtocolException;
use ReflectionException;
use ReflectionMethod;

/**
 * Handles the core functionality for the protocols
 *
 * @author Austin Bischoff <austin@codebeard.com>
 */
abstract class Protocol
{
    /**
     * Constants for class states
     */
    public const STATE_TESTING = 1;
    public const STATE_BETA = 2;
    public const STATE_STABLE = 3;
    public const STATE_DEPRECATED = 4;

    /**
     * Constants for packet keys
     */
    public const PACKET_ALL = 'all'; // Some protocols allow all data to be sent back in one call.
    public const PACKET_BASIC = 'basic';

    public const PACKET_CHALLENGE = 'challenge';
    public const PACKET_CHANNELS = 'channels'; // Voice servers
    public const PACKET_DETAILS = 'details';
    public const PACKET_INFO = 'info';
    public const PACKET_PLAYERS = 'players';
    public const PACKET_STATUS = 'status';
    public const PACKET_RULES = 'rules';
    public const PACKET_VERSION = 'version';

    /**
     * Transport constants
     */
    public const TRANSPORT_UDP = 'udp';
    public const TRANSPORT_TCP = 'tcp';
    public const TRANSPORT_SSL = 'ssl';
    public const TRANSPORT_TLS = 'tls';

    /**
     * Short name of the protocol
     */
    protected string $name = 'unknown';

    /**
     * The longer, fancier name for the protocol
     */
    protected string $name_long = 'unknown';

    /**
     * The difference between the client port and query port
     */
    protected int $port_diff = 0;

    /**
     * The transport method to use to actually send the data
     * Default is UDP
     */
    protected string $transport = self::TRANSPORT_UDP;

    /**
     * The protocol type used when querying the server
     */
    protected string $protocol = 'unknown';

    /**
     * Holds the valid packet types this protocol has available.
     *
     * @var array<string, string>
     */
    protected array $packets = [];

    /**
     * Original packet templates used when applying a new challenge.
     *
     * @var array<string, string>
     */
    private array $packet_templates = [];

    /**
     * Holds the response headers and the method to use to process them.
     *
     * @var array<int|string, string>
     */
    protected array $responses = [];

    /**
     * Holds the list of methods to run when parsing the packet response(s) data. These
     * methods should provide all the return information.
     *
     * @var list<string>
     */
    protected array $process_methods = [];

    /**
     * The packet responses received
     *
     * @var list<string>
     */
    protected array $packets_response = [];

    /**
     * Options for this protocol
     *
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * Define the state of this class
     */
    protected int $state = self::STATE_STABLE;

    /**
     * Fallback normalization map for protocol families that do not provide a more specific mapping.
     *
     * @var array<string, array<string, string|list<string>>>
     */
    protected array $normalize = [
        // General
        'general' => [
            // target       => source
            'dedicated'  => [
                'listenserver',
                'dedic',
                'bf2dedicated',
                'netserverdedicated',
                'bf2142dedicated',
                'dedicated',
            ],
            'gametype'   => ['ggametype', 'sigametype', 'matchtype'],
            'hostname'   => ['svhostname', 'servername', 'siname', 'name'],
            'mapname'    => ['map', 'simap'],
            'maxplayers' => ['svmaxclients', 'simaxplayers', 'maxclients', 'max_players'],
            'mod'        => ['game', 'gamedir', 'gamevariant'],
            'numplayers' => ['clients', 'sinumplayers', 'num_players'],
            'password'   => ['protected', 'siusepass', 'sineedpass', 'pswrd', 'gneedpass', 'auth', 'passsord'],
        ],
        // Indvidual
        'player'  => [
            'name'   => ['nick', 'player', 'playername', 'name'],
            'kills'  => ['kills'],
            'deaths' => ['deaths'],
            'score'  => ['kills', 'frags', 'skill', 'score'],
            'ping'   => ['ping'],
        ],
        // Team
        'team'    => [
            'name'  => ['name', 'teamname', 'team_t'],
            'score' => ['score', 'score_t'],
        ],
    ];

    /**
     * Quick join link
     */
    protected ?string $join_link = null;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        // Set the options for this specific instance of the class
        $this->options = $options;
        $this->packet_templates = $this->packets;
    }

    /**
     * String name of this class
     */
    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * Get the port difference between the server's client (game) and query ports
     */
    public function portDiff(): int
    {
        return $this->port_diff;
    }

    /**
     * "Find" the query port based off of the client port and port_diff
     *
     * This method is meant to be overloaded for more complex maths or lookup tables
     */
    public function findQueryPort(int $clientPort): int
    {
        return $clientPort + $this->port_diff;
    }

    /**
     * Return the join_link as defined by the protocol class
     */
    public function joinLink(): ?string
    {
        return $this->join_link;
    }

    /**
     * Short (callable) name of this class
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Long name of this class
     */
    public function nameLong(): string
    {
        return $this->name_long;
    }

    /**
     * Return the status of this Protocol Class
     */
    public function state(): int
    {
        return $this->state;
    }

    /**
     * Return the protocol property
     */
    public function getProtocol(): string
    {
        return $this->protocol;
    }

    /**
     * Get/set the transport type for this protocol
     */
    public function transport(?string $type = null): ?string
    {
        if ($type !== null) {
            $this->transport = $type;
        }

        return $this->transport;
    }

    /**
     * Set the options for the protocol call
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function options(array $options = []): array
    {
        if ($options !== []) {
            $this->options = $options;
        }

        return $this->options;
    }


    /*
     * Packet Section
     */

    /**
     * Return specific packet(s)
     *
     * @param list<string>|string $type
     * @return array<string, string>|string
     * @throws ProtocolException
     */
    public function getPacket(array|string $type = []): array|string
    {
        $packets = [];

        // We want an array of packets back
        if (is_array($type) && $type !== []) {
            // Loop the packets
            foreach ($this->packets as $packet_type => $packet_data) {
                // We want this packet
                if (in_array($packet_type, $type, true)) {
                    $packets[$packet_type] = $packet_data;
                }
            }
        } elseif ($type === '!challenge') {
            // Loop the packets
            foreach ($this->packets as $packet_type => $packet_data) {
                // Dont want challenge packets
                if ($packet_type !== self::PACKET_CHALLENGE) {
                    $packets[$packet_type] = $packet_data;
                }
            }
        } elseif (is_string($type)) {
            // Return specific packet type
            if (!array_key_exists($type, $this->packets)) {
                throw new ProtocolException("Unknown packet type '$type'.");
            }

            $packets = $this->packets[$type];
        } else {
            // Return all packets
            $packets = $this->packets;
        }

        // Return the packets
        return $packets;
    }

    /**
     * Get/set the packet response
     *
     * @param list<string>|null $response
     * @return list<string>
     */
    public function packetResponse(?array $response = null): array
    {
        if ($response !== null && $response !== []) {
            $this->packets_response = $response;
        }

        return $this->packets_response;
    }

    /**
     * Append packets received during a follow-up query round.
     *
     * @param list<string> $response
     */
    public function appendPacketResponse(array $response): void
    {
        foreach ($response as $packet) {
            $this->packets_response[] = $packet;
        }
    }

    /**
     * Return packets that must be sent after inspecting the responses received so far.
     *
     * Protocols can override this for pagination or other response-driven query flows.
     *
     * @return list<string>
     */
    public function getFollowUpPackets(): array
    {
        return [];
    }


    /*
     * Challenge section
     */

    /**
     * Determine whether or not this protocol has a challenge needed before querying
     */
    public function hasChallenge(): bool
    {
        return isset($this->packets[self::PACKET_CHALLENGE])
            && $this->packets[self::PACKET_CHALLENGE] !== '';
    }

    /**
     * Parse the challenge response and add it to the buffer items that need it.
     * This should be overloaded by extending class
     */
    public function challengeParseAndApply(Buffer $challenge_buffer): bool
    {
        return true;
    }

    /**
     * Apply the challenge string to all the packets that need it.
     */
    protected function challengeApply(string $challenge_string): bool
    {
        foreach ($this->packet_templates as $packet_type => $packet) {
            $this->packets[$packet_type] = str_replace('%s', $challenge_string, $packet);
        }

        return true;
    }

    /**
     * Converts a string from ISO-8859-1 to UTF-8.
     * This is a replacement for PHP's utf8_encode function that was deprecated with PHP 8.2.
     *
     * Source: symfony/polyfill-php72
     * See https://github.com/symfony/polyfill-php72/blob/bf44a9fd41feaac72b074de600314a93e2ae78e2/Php72.php#L24-L38
     *
     * @author Nicolas Grekas <p@tchwork.com>
     * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
     */
    public function convertToUtf8(string $s): string
    {
        $s .= $s;
        $len = strlen($s);

        $j = 0;

        for ($i = $len >> 1; $i < $len; ++$i, ++$j) {
            switch (true) {
                case $s[$i] < "\x80":
                    $s[$j] = $s[$i];

                    break;
                case $s[$i] < "\xC0":
                    $s[$j] = "\xC2";
                    $s[++$j] = $s[$i];

                    break;
                default:
                    $s[$j] = "\xC3";
                    $s[++$j] = chr(ord($s[$i]) - 64);

                    break;
            }
        }

        return substr($s, 0, $j);
    }

    /**
     * Get the normalize settings for the protocol
     *
     * @return array<string, array<string, string|list<string>>>
     */
    public function getNormalize(): array
    {
        return $this->normalize;
    }

    /**
     * Invoke a response handler declared by a protocol's response map.
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    protected function processResponseMethod(string $methodName, Buffer $buffer): array
    {
        try {
            $method = new ReflectionMethod($this, $methodName);
            $result = $method->invoke($this, $buffer);
        } catch (ReflectionException $exception) {
            throw new ProtocolException("Unknown response handler '$methodName'.", 0, $exception);
        }

        if (!is_array($result)) {
            throw new ProtocolException("Response handler '$methodName' did not return an array.");
        }

        $parsed = [];

        foreach ($result as $key => $value) {
            if (!is_string($key)) {
                throw new ProtocolException("Response handler '$methodName' returned a non-string result key.");
            }
            $parsed[$key] = $value;
        }

        return $parsed;
    }

    /**
     * Keep only string-keyed entries from decoded or otherwise untrusted data.
     *
     * @return array<string, mixed>
     */
    protected function normalizeStringKeyedArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_filter($value, is_string(...), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Convert a scalar value received from an untrusted response to an integer.
     */
    protected function normalizeInteger(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_float($value) && is_finite($value)) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    /*
     * General
     */

    /**
     * Generic method to allow protocol classes to do work right before the query is sent
     */
    public function beforeSend(Server $server): void
    {
    }

    /**
     * Method called to process query response data.  Each extending class has to have one of these functions.
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    abstract public function processResponse(): array;
}
