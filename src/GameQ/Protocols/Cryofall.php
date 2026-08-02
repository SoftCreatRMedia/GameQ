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
use GameQ\Server;

/**
 * CryoFall's reverse-engineered, stateful UDP status protocol.
 *
 * The game source repository does not include the engine networking code. This implementation is therefore
 * based on the packet sequence documented by LGSL and remains beta until it can be verified against protocol
 * documentation or current packet captures.
 *
 * @see https://github.com/AtomicTorchStudio/CryoFall
 * @see https://github.com/tltneon/lgsl/commit/dcc7eb844a6fff3885131c80d301e74c07b78190
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Cryofall extends Protocol
{
    private const HANDSHAKE_PACKET
        = "\x05\x0B\x00\x00\x00\x86\x76\x41\x31\xA0\x87\xDB\x08\x10\x02\x00"
        . "\x55\xF0\x86\xFF\xDE\x58\x00\x00\x00\x00\x00\x00\x00\x00\x08\x00\x00\x00CryoFall";

    private const NEGOTIATION_PACKET
        = "\x0C\x0A\x00\x01\x00\x00\x02\x00\x05\x60\x02\xE8\x03\x07\x00\x00\x06\x5F\x02\x20\x4E\x01";

    private const STATUS_PACKET = "\x01\x00\x00\x02\x00\x05\x60\x02\xE8\x03";

    private const STATUS_RETRY_PACKET = "\x00\x06\x61\x02\x20\x4E\x02";

    protected string $protocol = 'cryofall';

    protected string $name = 'cryofall';

    protected string $name_long = 'CryoFall';

    protected int $state = self::STATE_BETA;

    protected ?string $join_link = 'steam://connect/%s:%d/';

    protected array $packets = [
        self::PACKET_STATUS => self::HANDSHAKE_PACKET,
    ];

    protected array $normalize = [
        'general' => [
            'dedicated' => 'dedicated',
            'hostname' => 'hostname',
            'mapname' => 'map',
            'maxplayers' => 'max_players',
            'numplayers' => 'num_players',
        ],
    ];

    private int $queryStage = 0;

    private int $processedResponseCount = 0;

    public function beforeSend(Server $server): void
    {
        $this->queryStage = 0;
        $this->processedResponseCount = 0;
        $this->packets_response = [];
    }

    /**
     * Advance the handshake only after the server acknowledges the previous packet.
     *
     * @return list<string>
     */
    public function getFollowUpPackets(): array
    {
        $responseCount = count($this->packets_response);

        if ($responseCount <= $this->processedResponseCount) {
            return [];
        }

        $latestResponse = $this->packets_response[$responseCount - 1];
        $this->processedResponseCount = $responseCount;

        return match ($this->queryStage++) {
            0 => [self::NEGOTIATION_PACKET],
            1 => [self::STATUS_PACKET],
            2 => strlen($latestResponse) < 12 ? [self::STATUS_RETRY_PACKET] : [],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        $response = $this->packets_response[2] ?? null;

        if (!is_string($response) || strlen($response) < 12) {
            $response = $this->packets_response[3] ?? null;
        }

        if (!is_string($response) || strlen($response) < 12) {
            if ($this->packets_response === []) {
                return [];
            }

            return [
                'dedicated' => true,
                'map' => 'CryoFall',
                'query_limited' => true,
            ];
        }

        $buffer = new Buffer($response);
        $buffer->read(11);
        $result = new Result();
        $result->add('dedicated', true);
        $result->add('map', 'CryoFall');
        $result->add('hostname', $this->readString16($buffer));
        $buffer->read(2);
        $result->add('version', $this->readString16($buffer));
        $result->add('num_players', $buffer->readInt16());
        $result->add('max_players', $buffer->readInt16());
        $buffer->read(3);
        $result->add('description', $this->readString16($buffer));

        $metadataLength = $buffer->readInt16();

        if ($metadataLength < 8) {
            throw new ProtocolException('CryoFall server metadata length is invalid.');
        }

        $buffer->read($metadataLength - 8);
        $result->add('guid', strtoupper(bin2hex(strrev($buffer->read(16)))));
        $buffer->read(8);

        $mods = [];
        $modCount = $buffer->readInt8();

        for ($index = 0; $index < $modCount; ++$index) {
            $mods[] = [
                'name' => $this->readString16($buffer),
                'version' => $this->readString16($buffer),
                'description' => $this->readString16($buffer),
            ];
            $buffer->read(2);
        }

        $result->add('mods', $mods);
        $options = [];
        $optionCount = $buffer->readInt8();

        for ($index = 0; $index < $optionCount; ++$index) {
            $options[] = $this->readString16($buffer);
        }

        $result->add('options', $options);
        $result->add('community_server', $buffer->readInt8() !== 0);
        $result->add('no_client_mods', $buffer->readInt8() !== 0);

        return $result->fetch();
    }

    /**
     * @throws ProtocolException
     */
    private function readString16(Buffer $buffer): string
    {
        return $buffer->read($buffer->readInt16());
    }
}
