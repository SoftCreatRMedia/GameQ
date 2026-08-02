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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace GameQ\Protocols;

use GameQ\Buffer;
use GameQ\Exception\ProtocolException;
use GameQ\Protocol;
use GameQ\Result;

/**
 * HytaleONE OneQuery protocol with V2 support and automatic V1 fallback.
 *
 * @see https://github.com/HytaleOne/onequery-plugin
 * @see https://github.com/HytaleOne/onequery-plugin/blob/main/docs/PROTOCOL.md
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Hytaleone extends Protocol
{
    public const PACKET_HEADER = "HYREPLY\x00";

    public const V2_PACKET_HEADER = 'ONEREPLY';

    private const V2_REQUEST_HEADER = 'ONEQUERY';

    private const V2_VERSION = 0x01;

    private const V2_TYPE_CHALLENGE = 0x00;

    private const V2_TYPE_BASIC = 0x01;

    private const V2_TYPE_PLAYERS = 0x02;

    private const V2_FLAG_REQUEST_HAS_AUTH_TOKEN = 0x0001;

    private const V2_FLAG_RESPONSE_HAS_MORE_PLAYERS = 0x0001;

    private const V2_FLAG_RESPONSE_AUTH_REQUIRED = 0x0002;

    private const V2_FLAG_RESPONSE_IS_NETWORK = 0x0010;

    private const V2_FLAG_RESPONSE_HAS_ADDRESS = 0x0020;

    private const V2_TLV_SERVER_INFO = 0x0001;

    private const V2_TLV_PLAYER_LIST = 0x0002;

    protected string $protocol = 'hytaleone';

    protected string $name = 'hytaleone';

    protected string $name_long = 'HytaleONE';

    protected int $state = self::STATE_STABLE;

    protected array $packets = [
        self::PACKET_CHALLENGE => self::V2_REQUEST_HEADER . "\x00",
        self::PACKET_ALL => "HYQUERY\x00\x01",
    ];

    protected array $normalize = [
        'general' => [
            'hostname' => 'hostname',
            'numplayers' => 'num_players',
            'maxplayers' => 'max_players',
        ],
        'player' => [
            'name' => 'name',
        ],
    ];

    private bool $usingV2 = false;

    private ?string $challengeToken = null;

    /** @var array<int, true> */
    private array $requestedPlayerOffsets = [0 => true];

    private int $nextRequestId = 3;

    /**
     * Switch from the default V1 fallback packet to V2 after a valid challenge response.
     *
     * @throws ProtocolException
     */
    public function challengeParseAndApply(Buffer $challenge_buffer): bool
    {
        if (
            $challenge_buffer->getLength() !== 48
            || $challenge_buffer->read(8) !== self::V2_PACKET_HEADER
            || $challenge_buffer->readInt8() !== self::V2_TYPE_CHALLENGE
        ) {
            return false;
        }

        $challengeToken = $challenge_buffer->read(32);

        if ($challenge_buffer->read(7) !== str_repeat("\x00", 7)) {
            return false;
        }

        $this->usingV2 = true;
        $this->challengeToken = $challengeToken;
        $this->requestedPlayerOffsets = [0 => true];
        $this->nextRequestId = 3;
        unset($this->packets[self::PACKET_ALL]);

        $this->packets[self::PACKET_BASIC] = $this->buildV2Query(
            self::V2_TYPE_BASIC,
            $challengeToken,
            1,
            0,
        );

        if (($this->options['query_players'] ?? true) !== false) {
            $this->packets[self::PACKET_PLAYERS] = $this->buildV2Query(
                self::V2_TYPE_PLAYERS,
                $challengeToken,
                2,
                0,
            );
        } else {
            $this->requestedPlayerOffsets = [];
        }

        return true;
    }

    /**
     * Request the next player-list page advertised by a V2 response.
     *
     * @return list<string>
     * @throws ProtocolException
     */
    public function getFollowUpPackets(): array
    {
        if (!$this->usingV2 || ($this->options['query_players'] ?? true) === false) {
            return [];
        }

        foreach ($this->parseV2Packets() as $packet) {
            if (($packet['flags'] & self::V2_FLAG_RESPONSE_HAS_MORE_PLAYERS) === 0) {
                continue;
            }

            foreach ($packet['tlvs'] as $tlv) {
                if ($tlv['type'] !== self::V2_TLV_PLAYER_LIST) {
                    continue;
                }

                $page = $this->readV2PlayerPageHeader($tlv['data']);
                $nextOffset = $page['offset'] + $page['count'];

                if (
                    $page['count'] < 1
                    || $nextOffset >= $page['total']
                    || isset($this->requestedPlayerOffsets[$nextOffset])
                ) {
                    continue;
                }

                $this->requestedPlayerOffsets[$nextOffset] = true;

                return [$this->buildV2Query(
                    self::V2_TYPE_PLAYERS,
                    $this->getChallengeToken(),
                    $this->nextRequestId++,
                    $nextOffset,
                )];
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        foreach ($this->packets_response as $response) {
            if (str_starts_with($response, self::V2_PACKET_HEADER)) {
                return $this->processV2Responses();
            }
        }

        return $this->processV1Responses();
    }

    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    private function processV2Responses(): array
    {
        $results = [];
        $playersByOffset = [];
        $playersTotal = 0;
        $hasPlayerPage = false;

        foreach ($this->parseV2Packets() as $packet) {
            $results['onequery_version'] = $packet['version'];

            if (($packet['flags'] & self::V2_FLAG_RESPONSE_AUTH_REQUIRED) !== 0) {
                $results['auth_required'] = true;
            }

            if (($packet['flags'] & self::V2_FLAG_RESPONSE_IS_NETWORK) !== 0) {
                $results['network'] = true;
            }

            foreach ($packet['tlvs'] as $tlv) {
                if ($tlv['type'] === self::V2_TLV_SERVER_INFO) {
                    foreach ($this->readV2ServerInfo($tlv['data'], $packet['flags']) as $key => $value) {
                        $results[$key] = $value;
                    }

                    continue;
                }

                if ($tlv['type'] !== self::V2_TLV_PLAYER_LIST) {
                    continue;
                }

                $hasPlayerPage = true;
                $page = $this->readV2PlayerPage($tlv['data']);
                $playersTotal = max($playersTotal, $page['total']);

                foreach ($page['players'] as $index => $player) {
                    $playersByOffset[$page['offset'] + $index] = $player;
                }
            }
        }

        if ($hasPlayerPage) {
            ksort($playersByOffset);
            $results['players'] = array_values($playersByOffset);
            $results['players_total'] = $playersTotal;
            $results['players_returned'] = count($playersByOffset);
            $results['players_truncated'] = count($playersByOffset) < $playersTotal;
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    private function processV1Responses(): array
    {
        $results = [];

        foreach ($this->packets_response as $response) {
            $buffer = new Buffer($response);

            if ($buffer->getLength() < 9 || $buffer->read(8) !== self::PACKET_HEADER) {
                continue;
            }

            $type = $buffer->readInt8();

            if ($type !== 0x00 && $type !== 0x01) {
                throw new ProtocolException("Unsupported HytaleONE V1 response type '$type'.");
            }

            $result = new Result();
            $result->add('hostname', $this->readString16($buffer));
            $result->add('motd', $this->readString16($buffer));
            $result->add('num_players', $buffer->readInt32());
            $result->add('max_players', $buffer->readInt32());
            $result->add('port', $buffer->readInt16());
            $result->add('version', $this->readString16($buffer));
            $result->add('protocol_version', $buffer->readInt32());
            $result->add('protocol_hash', $this->readString16($buffer));

            if ($type === 0x01) {
                $this->readV1Players($buffer, $result);
                $result->add('plugins', $this->readV1Plugins($buffer));
            }

            if ($buffer->getLength() > 0 && $buffer->getLength() < 3) {
                throw new ProtocolException('HytaleONE V1 capability metadata is truncated.');
            }

            if ($buffer->getLength() >= 3) {
                $capabilities = $buffer->readInt16();
                $result->add('onequery_capabilities', $capabilities);
                $result->add('onequery_v2_version', $buffer->readInt8());
                $result->add('network', ($capabilities & 0x02) !== 0);
            }

            foreach ($result->fetch() as $key => $value) {
                $results[$key] = $value;
            }
        }

        return $results;
    }

    /**
     * @return list<array{
     *     version: int,
     *     flags: int,
     *     request_id: int,
     *     tlvs: list<array{type: int, data: string}>
     * }>
     * @throws ProtocolException
     */
    private function parseV2Packets(): array
    {
        $packets = [];

        foreach ($this->packets_response as $response) {
            $buffer = new Buffer($response);

            if ($buffer->getLength() < 17 || $buffer->read(8) !== self::V2_PACKET_HEADER) {
                continue;
            }

            $version = $buffer->readInt8();

            if ($version !== self::V2_VERSION) {
                throw new ProtocolException("Unsupported OneQuery V2 response version '$version'.");
            }

            $flags = $buffer->readInt16();
            $requestId = $buffer->readInt32();
            $payload = new Buffer($buffer->read($buffer->readInt16()));
            $tlvs = [];

            while ($payload->getLength() > 0) {
                if ($payload->getLength() < 4) {
                    throw new ProtocolException('OneQuery V2 TLV header is truncated.');
                }

                $type = $payload->readInt16();
                $tlvs[] = [
                    'type' => $type,
                    'data' => $payload->read($payload->readInt16()),
                ];
            }

            $packets[] = [
                'version' => $version,
                'flags' => $flags,
                'request_id' => $requestId,
                'tlvs' => $tlvs,
            ];
        }

        return $packets;
    }

    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    private function readV2ServerInfo(string $data, int $flags): array
    {
        $buffer = new Buffer($data);
        $server = [
            'hostname' => $this->readString16($buffer),
            'motd' => $this->readString16($buffer),
            'num_players' => $buffer->readInt32(),
            'max_players' => $buffer->readInt32(),
            'version' => $this->readString16($buffer),
            'protocol_version' => $buffer->readInt32(),
            'protocol_hash' => $this->readString16($buffer),
        ];

        if (($flags & self::V2_FLAG_RESPONSE_HAS_ADDRESS) !== 0) {
            $server['host'] = $this->readString16($buffer);
            $server['port'] = $buffer->readInt16();
        }

        return $server;
    }

    /**
     * @return array{total: int, count: int, offset: int}
     * @throws ProtocolException
     */
    private function readV2PlayerPageHeader(string $data): array
    {
        $buffer = new Buffer($data);
        $total = $buffer->readInt32();
        $count = $buffer->readInt32();
        $offset = $buffer->readInt32();

        if ($offset > $total || $count > ($total - $offset)) {
            throw new ProtocolException('OneQuery V2 player-page bounds are invalid.');
        }

        return [
            'total' => $total,
            'count' => $count,
            'offset' => $offset,
        ];
    }

    /**
     * @return array{total: int, offset: int, players: list<array{name: string, uuid: string}>}
     * @throws ProtocolException
     */
    private function readV2PlayerPage(string $data): array
    {
        $header = $this->readV2PlayerPageHeader($data);
        $buffer = new Buffer($data);
        $buffer->skip(12);

        if ($header['count'] > intdiv($buffer->getLength(), 18)) {
            throw new ProtocolException('OneQuery V2 player count exceeds the response length.');
        }

        $players = [];

        for ($index = 0; $index < $header['count']; ++$index) {
            $players[] = [
                'name' => $this->readString16($buffer),
                'uuid' => $this->readUuid($buffer),
            ];
        }

        return [
            'total' => $header['total'],
            'offset' => $header['offset'],
            'players' => $players,
        ];
    }

    /**
     * @throws ProtocolException
     */
    private function readString16(Buffer $buffer): string
    {
        return $buffer->read($buffer->readInt16());
    }

    /**
     * @throws ProtocolException
     */
    private function readV1Players(Buffer $buffer, Result $result): void
    {
        $playerCount = $buffer->readInt32();

        if ($playerCount > intdiv($buffer->getLength(), 18)) {
            throw new ProtocolException('HytaleONE V1 player count exceeds the response length.');
        }

        for ($index = 0; $index < $playerCount; ++$index) {
            $result->addPlayer('name', $this->readString16($buffer));
            $result->addPlayer('uuid', $this->readUuid($buffer));
        }
    }

    /**
     * @return list<array{id: string, version: string, enabled: bool}>
     * @throws ProtocolException
     */
    private function readV1Plugins(Buffer $buffer): array
    {
        $pluginCount = $buffer->readInt32();

        if ($pluginCount > intdiv($buffer->getLength(), 5)) {
            throw new ProtocolException('HytaleONE V1 plugin count exceeds the response length.');
        }

        $plugins = [];

        for ($index = 0; $index < $pluginCount; ++$index) {
            $plugins[] = [
                'id' => $this->readString16($buffer),
                'version' => $this->readString16($buffer),
                'enabled' => $buffer->readInt8() !== 0,
            ];
        }

        return $plugins;
    }

    /**
     * @throws ProtocolException
     */
    private function readUuid(Buffer $buffer): string
    {
        $hex = bin2hex($buffer->read(16));

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20),
        );
    }

    /**
     * @throws ProtocolException
     */
    private function buildV2Query(int $type, string $challengeToken, int $requestId, int $offset): string
    {
        if (strlen($challengeToken) !== 32) {
            throw new ProtocolException('OneQuery V2 challenge tokens must contain exactly 32 bytes.');
        }

        $authToken = $this->options['auth_token'] ?? null;

        if ($authToken !== null && !is_string($authToken)) {
            throw new ProtocolException("The HytaleONE 'auth_token' option must be a string.");
        }

        if (is_string($authToken) && strlen($authToken) > 65535) {
            throw new ProtocolException("The HytaleONE 'auth_token' option is too long.");
        }

        $flags = is_string($authToken) ? self::V2_FLAG_REQUEST_HAS_AUTH_TOKEN : 0;
        $packet = self::V2_REQUEST_HEADER
            . chr($type)
            . $challengeToken
            . pack('V', $requestId)
            . pack('v', $flags)
            . pack('V', $offset);

        if (is_string($authToken)) {
            $packet .= pack('v', strlen($authToken)) . $authToken;
        }

        return $packet;
    }

    /**
     * @throws ProtocolException
     */
    private function getChallengeToken(): string
    {
        if ($this->challengeToken === null) {
            throw new ProtocolException('OneQuery V2 challenge negotiation has not completed.');
        }

        return $this->challengeToken;
    }
}
