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

use GameQ\Exception\ProtocolException;
use GameQ\Protocol;
use GameQ\Result;
use GameQ\Server;

/**
 * Metin2 channel-status protocol.
 *
 * The public query reports channel availability and load states, but does not
 * include an exact player count. Servers that explicitly allow the querying
 * host through ADMINPAGE_IP can opt into the read-only IS_SERVER_UP and
 * USER_COUNT commands by setting the `admin_page_query` protocol option to
 * true.
 *
 * @see https://github.com/NakiuS/Metin2Client/blob/master/source/UserInterface/ServerStateChecker.cpp
 * @see https://github.com/willianmarquess/open-mt2/blob/master/src/core/interface/networking/packets/packet/out/ServerStatusPacket.ts
 * @see https://github.com/renoki-games/Metin2BE-Server-Source/blob/master/Server/game/src/input.cpp
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Metin2 extends Protocol
{
    private const CHANNEL_STATUS_REQUEST = "\xCE";

    private const CHANNEL_STATUS_RESPONSE = "\xD2";

    private const ADMIN_PAGE_SERVER_UP_REQUEST = "\x40IS_SERVER_UP\x0A";

    private const ADMIN_PAGE_USER_COUNT_REQUEST = "\x40USER_COUNT\x0A";

    private const MAX_CHANNELS = 64;

    private const MAX_PLAYER_COUNT = 10_000_000;

    /** @var array<int, string> */
    private const STATUS_NAMES = [
        0 => 'closed',
        1 => 'normal',
        2 => 'busy',
        3 => 'full',
    ];

    protected string $protocol = 'metin2';

    protected string $name = 'metin2';

    protected string $name_long = 'Metin2';

    protected string $transport = self::TRANSPORT_TCP;

    protected int $state = self::STATE_BETA;

    protected array $packets = [
        self::PACKET_STATUS => self::CHANNEL_STATUS_REQUEST,
    ];

    protected array $normalize = [
        'general' => [
            'dedicated' => 'dedicated',
            'numplayers' => 'num_players',
        ],
    ];

    private bool $adminPageQuery = false;

    private int $queryPort = 0;

    /**
     * @throws ProtocolException
     */
    public function beforeSend(Server $server): void
    {
        $this->queryPort = $server->portQuery();
        $adminPageQuery = $this->options['admin_page_query'] ?? false;

        if ($adminPageQuery === 0 || $adminPageQuery === 1) {
            $adminPageQuery = (bool) $adminPageQuery;
        }

        if (!is_bool($adminPageQuery)) {
            throw new ProtocolException("The Metin2 'admin_page_query' option must be a boolean.");
        }

        $this->adminPageQuery = $adminPageQuery;

        if ($adminPageQuery) {
            $this->packets = [
                self::PACKET_STATUS => self::ADMIN_PAGE_SERVER_UP_REQUEST,
                self::PACKET_DETAILS => self::ADMIN_PAGE_USER_COUNT_REQUEST,
            ];

            return;
        }

        $this->packets = [
            self::PACKET_STATUS => self::CHANNEL_STATUS_REQUEST,
        ];
    }

    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        $response = implode('', $this->packets_response);

        if ($response === '') {
            throw new ProtocolException('Metin2 returned an empty response.');
        }

        if ($this->adminPageQuery) {
            return $this->processAdminPageResponse($response);
        }

        return $this->processChannelStatusResponse($response);
    }

    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    private function processAdminPageResponse(string $response): array
    {
        $countPattern = '/(\d+) +(\d+) +(\d+) +(\d+) +(\d+)(?:\r?\n)?\z/';

        if (preg_match($countPattern, $response, $countMatches, PREG_OFFSET_CAPTURE) !== 1) {
            throw new ProtocolException(
                'Metin2 did not return valid IS_SERVER_UP and USER_COUNT responses. '
                . 'Ensure that the querying IP is allowed by ADMINPAGE_IP.',
            );
        }

        $availabilityResponse = substr($response, 0, $countMatches[0][1]);

        if (preg_match('/(YES|NO)\r?\n\z/', $availabilityResponse, $availabilityMatches) !== 1) {
            throw new ProtocolException(
                'Metin2 did not return valid IS_SERVER_UP and USER_COUNT responses. '
                . 'Ensure that the querying IP is allowed by ADMINPAGE_IP.',
            );
        }

        $counts = [];

        for ($index = 1; $index <= 5; ++$index) {
            $value = $countMatches[$index][0];

            if (!ctype_digit($value)) {
                throw new ProtocolException('Metin2 returned an invalid USER_COUNT value.');
            }

            $count = (int) $value;

            if ($count > self::MAX_PLAYER_COUNT) {
                throw new ProtocolException('Metin2 returned an implausible USER_COUNT value.');
            }

            $counts[] = $count;
        }

        $result = new Result();
        $result->add('query_mode', 'admin_page');
        $result->add('status', $availabilityMatches[1] === 'YES' ? 1 : 0);
        $result->add('status_name', $availabilityMatches[1] === 'YES' ? 'normal' : 'closed');
        $result->add('accepting_players', $availabilityMatches[1] === 'YES');
        $result->add('num_players', $counts[4]);
        $result->add('num_players_total', $counts[0]);
        $result->add('players_shinsoo', $counts[1]);
        $result->add('players_chunjo', $counts[2]);
        $result->add('players_jinno', $counts[3]);
        $result->add('players_local', $counts[4]);
        $result->add('dedicated', true);

        return $result->fetch();
    }

    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    private function processChannelStatusResponse(string $response): array
    {
        $candidates = [];
        $offset = -1;

        while (($offset = strpos($response, self::CHANNEL_STATUS_RESPONSE, $offset + 1)) !== false) {
            $candidate = $this->parseChannelStatusCandidate($response, $offset);

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        if (count($candidates) !== 1) {
            throw new ProtocolException('Metin2 returned an invalid or ambiguous channel-status response.');
        }

        $candidate = $candidates[0];
        $channels = $candidate['channels'];
        $result = new Result();
        $result->add('query_mode', 'channel_status');
        $result->add('status_format', $candidate['format']);
        $result->add('channel_count', count($channels));
        $result->add('channels', $channels);
        $result->add('dedicated', true);

        $selectedChannel = null;

        foreach ($channels as $channel) {
            if ($channel['port'] === $this->queryPort) {
                $selectedChannel = $channel;

                break;
            }
        }

        if ($selectedChannel === null && count($channels) === 1) {
            $selectedChannel = $channels[0];
        }

        if ($selectedChannel !== null) {
            $result->add('status', $selectedChannel['status']);
            $result->add('status_name', $selectedChannel['status_name']);
            $result->add('accepting_players', in_array($selectedChannel['status'], [1, 2], true));

            if (isset($selectedChannel['players'])) {
                $result->add('num_players', $selectedChannel['players']);
            }
        }

        return $result->fetch();
    }

    /**
     * @return array{
     *     format: 'standard'|'extended',
     *     channels: list<array{port: int, status: int, status_name: string, players?: int}>
     * }|null
     *
     * @throws ProtocolException
     */
    private function parseChannelStatusCandidate(string $response, int $offset): ?array
    {
        $responseLength = strlen($response);

        if ($responseLength - $offset < 6) {
            return null;
        }

        $channelCount = $this->readUint32Le($response, $offset + 1);

        if ($channelCount > self::MAX_CHANNELS) {
            return null;
        }

        foreach (['standard' => 3, 'extended' => 7] as $format => $entrySize) {
            if ($channelCount === 0 && $format === 'extended') {
                continue;
            }

            $packetLength = 6 + ($channelCount * $entrySize);

            if ($responseLength - $offset !== $packetLength || ord($response[$responseLength - 1]) !== 1) {
                continue;
            }

            $channels = [];
            $position = $offset + 5;

            for ($channelIndex = 0; $channelIndex < $channelCount; ++$channelIndex) {
                $port = $this->readUint16Le($response, $position);
                $status = ord($response[$position + 2]);

                if ($port < 1 || !array_key_exists($status, self::STATUS_NAMES)) {
                    continue 2;
                }

                $channel = [
                    'port' => $port,
                    'status' => $status,
                    'status_name' => self::STATUS_NAMES[$status],
                ];

                if ($format === 'extended') {
                    $players = $this->readUint32Le($response, $position + 3);

                    if ($players > self::MAX_PLAYER_COUNT) {
                        continue 2;
                    }

                    $channel['players'] = $players;
                }

                $channels[] = $channel;
                $position += $entrySize;
            }

            return [
                'format' => $format,
                'channels' => $channels,
            ];
        }

        return null;
    }

    /**
     * @throws ProtocolException
     */
    private function readUint16Le(string $data, int $offset): int
    {
        $value = unpack('vvalue', substr($data, $offset, 2));

        if ($value === false || !isset($value['value']) || !is_int($value['value'])) {
            throw new ProtocolException('Metin2 returned a truncated 16-bit value.');
        }

        return $value['value'];
    }

    /**
     * @throws ProtocolException
     */
    private function readUint32Le(string $data, int $offset): int
    {
        $value = unpack('Vvalue', substr($data, $offset, 4));

        if ($value === false || !isset($value['value']) || !is_int($value['value'])) {
            throw new ProtocolException('Metin2 returned a truncated 32-bit value.');
        }

        return $value['value'];
    }
}
