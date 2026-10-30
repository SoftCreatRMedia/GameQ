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
use GameQ\Result;

/**
 * Vice City Multiplayer server protocol.
 *
 * @see https://wiki.vc-mp.org/wiki/Query_Mechanism
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Vcmp extends Openmp
{
    protected string $responseMagicHeader = 'MP04';
    protected string $protocol = 'vcmp';
    protected string $name = 'vcmp';
    protected string $name_long = 'Vice City Multiplayer';
    protected ?string $join_link = 'vcmp://%s:%d/';
    protected array $packets = [
        self::PACKET_STATUS => 'VCMP%si',
        self::PACKET_PLAYERS => 'VCMP%sc',
    ];
    protected array $responses = [
        "\x69" => 'processStatus',
        "\x63" => 'processPlayers',
    ];

    /** @return array<string, mixed>
     *
     * @throws ProtocolException
     */
    protected function processStatus(Buffer $buffer): array
    {
        $result = new Result();
        $result->add('version', rtrim($buffer->read(12), "\0"));
        $result->add('dedicated', true);
        $result->add('password', (bool) $buffer->readInt8());
        $result->add('num_players', $buffer->readInt16());
        $result->add('max_players', $buffer->readInt16());
        $result->add('servername', $this->convertToUtf8($buffer->read($buffer->readInt32())));
        $result->add('gametype', $this->convertToUtf8($buffer->read($buffer->readInt32())));
        $result->add('mapname', $this->convertToUtf8($buffer->read($buffer->readInt32())));

        return $result->fetch();
    }

    /** @return array<string, mixed>
     *
     * @throws ProtocolException
     */
    protected function processPlayers(Buffer $buffer): array
    {
        $result = new Result();
        $count = $buffer->readInt16();
        $result->add('num_players', $count);

        for ($i = 0; $i < $count; ++$i) {
            $result->addPlayer('name', $this->convertToUtf8($buffer->readPascalString()));
        }

        return $result->fetch();
    }
}
