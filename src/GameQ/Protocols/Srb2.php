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
 */

namespace GameQ\Protocols;

use GameQ\Buffer;
use GameQ\Exception\ProtocolException;
use GameQ\Protocol;
use GameQ\Result;

/**
 * Sonic Robo Blast 2 server protocol.
 *
 * @see https://github.com/STJr/SRB2/blob/master/src/netcode/protocol.h
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Srb2 extends Protocol
{
    private const CHECKSUM_BASE = 0x1234567;

    private const SERVER_INFO_VERSION = 5;

    private const PT_ASKINFO = 12;

    private const PT_SERVERINFO = 13;

    private const CURRENT_VERSION = 202;

    protected string $protocol = 'srb2';
    protected string $name = 'srb2';
    protected string $name_long = 'Sonic Robo Blast 2';
    protected ?string $join_link = 'srb2://%s:%d/';
    protected array $normalize = ['general' => [
        'hostname' => 'name', 'mapname' => 'map', 'maxplayers' => 'max_players',
        'numplayers' => 'num_players', 'dedicated' => 'dedicated',
    ]];

    public function __construct(array $options = [])
    {
        parent::__construct($options);
        $body = "\0\0" . chr(self::PT_ASKINFO) . "\0" . chr(self::CURRENT_VERSION) . str_repeat("\0", 4);
        $checksum = self::CHECKSUM_BASE;

        for ($i = 0, $length = strlen($body); $i < $length; ++$i) {
            $checksum += ord($body[$i]) * ($i + 1);
        }

        $this->packets = [self::PACKET_STATUS => pack('V', $checksum) . $body];
    }

    /** @return array<string, mixed>
     *
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        $packet = $this->findServerInfoPacket();

        if (strlen($packet) < 158) {
            throw new ProtocolException('SRB2 returned a truncated server-info packet.');
        }

        $checksumBuffer = new Buffer($packet);
        $checksum = $checksumBuffer->readInt32();
        $expectedChecksum = self::CHECKSUM_BASE;

        for ($i = 4, $length = strlen($packet); $i < $length; ++$i) {
            $expectedChecksum += ord($packet[$i]) * ($i - 3);
        }

        if ($checksum !== $expectedChecksum) {
            throw new ProtocolException('SRB2 response checksum validation failed.');
        }

        $buffer = new Buffer($packet);
        $buffer->skip(6);

        if ($buffer->readInt8() !== self::PT_SERVERINFO) {
            throw new ProtocolException('SRB2 returned an unexpected packet type.');
        }

        $buffer->skip();
        $result = new Result();
        $formatMarker = $buffer->readInt8();
        $packetVersion = $buffer->readInt8();

        if ($formatMarker !== 255 || $packetVersion !== self::SERVER_INFO_VERSION) {
            throw new ProtocolException('SRB2 returned an unsupported server-info format.');
        }

        $result->add('format_marker', $formatMarker);
        $result->add('packet_version', $packetVersion);
        $result->add('application', rtrim($buffer->read(16), "\0"));
        $version = $buffer->readInt8();
        $subVersion = $buffer->readInt8();
        $result->add('version', intdiv($version, 100) . '.' . ($version % 100) . '.' . $subVersion);
        $result->add('num_players', $buffer->readInt8());
        $result->add('max_players', $buffer->readInt8());
        $buffer->skip();
        $result->add('mode', rtrim($buffer->read(24), "\0"));
        $result->add('modified_game', (bool) $buffer->readInt8());
        $result->add('cheats_enabled', (bool) $buffer->readInt8());
        $flags = $buffer->readInt8();
        $result->add('dedicated', (bool) ($flags & 0x40));
        $result->add('lots_of_addons', (bool) ($flags & 0x20));
        $result->add('files_needed', $buffer->readInt8());
        $buffer->skip(8);
        $result->add('name', rtrim($buffer->read(32), "\0"));
        $result->add('map_name', rtrim($buffer->read(8), "\0"));
        $result->add('map', rtrim($buffer->read(33), "\0"));
        $buffer->skip(16);
        $result->add('act', $buffer->readInt8());
        $result->add('zone', (bool) $buffer->readInt8());

        return $result->fetch();
    }

    /**
     * @throws ProtocolException
     */
    private function findServerInfoPacket(): string
    {
        $serverInfoPackets = [];

        foreach ($this->packets_response as $packet) {
            if (strlen($packet) >= 7 && ord($packet[6]) === self::PT_SERVERINFO) {
                $serverInfoPackets[] = $packet;
            }
        }

        if (count($serverInfoPackets) !== 1) {
            throw new ProtocolException('SRB2 returned an unexpected number of server-info packets.');
        }

        return $serverInfoPackets[0];
    }
}
