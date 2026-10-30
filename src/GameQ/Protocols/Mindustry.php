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
 * Mindustry LAN discovery protocol.
 *
 * @see https://github.com/Anuken/Mindustry/blob/master/core/src/mindustry/net/NetworkIO.java
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Mindustry extends Protocol
{
    protected string $protocol = 'mindustry';
    protected string $name = 'mindustry';
    protected string $name_long = 'Mindustry';
    protected array $packets = [self::PACKET_STATUS => "\xFE\x01"];
    protected array $normalize = ['general' => [
        'hostname' => 'name', 'mapname' => 'map', 'maxplayers' => 'max_players',
        'numplayers' => 'num_players', 'gametype' => 'gamemode',
    ]];

    /** @return array<string, mixed>
     *
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        $buffer = new Buffer(implode('', $this->packets_response), Buffer::NUMBER_TYPE_BIGENDIAN);
        $result = new Result();
        $result->add('name', $this->readString($buffer));
        $result->add('map', $this->readString($buffer));
        $result->add('num_players', $buffer->readInt32());
        $result->add('wave', $buffer->readInt32Signed());
        $result->add('version', (string) $buffer->readInt32Signed());
        $result->add('version_type', $this->readString($buffer));
        $mode = $buffer->readInt8();
        $result->add('gamemode', ['survival', 'sandbox', 'attack', 'pvp', 'editor'][$mode] ?? (string) $mode);
        $result->add('max_players', $buffer->readInt32Signed());
        $result->add('description', $this->readString($buffer));
        $result->add('mode_name', $this->readString($buffer));
        $result->add('game_port', $buffer->readInt16());
        $result->add('password', false);

        return $result->fetch();
    }

    /**
     * @throws ProtocolException
     */
    private function readString(Buffer $buffer): string
    {
        $length = $buffer->readInt8();

        if ($length > $buffer->getLength()) {
            throw new ProtocolException('Mindustry string exceeds the response length.');
        }

        return $buffer->read($length);
    }
}
