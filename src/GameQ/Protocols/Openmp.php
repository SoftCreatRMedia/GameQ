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
 * open.mp server protocol.
 *
 * @see https://open.mp/docs/tutorials/QueryMechanism
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Openmp extends Samp
{
    protected string $protocol = 'openmp';
    protected string $name = 'openmp';
    protected string $name_long = 'open.mp';
    protected array $packets = [
        self::PACKET_STATUS => 'SAMP%si',
        self::PACKET_PLAYERS => 'SAMP%sc',
        self::PACKET_RULES => 'SAMP%sr',
    ];
    protected array $responses = [
        "\x69" => 'processStatus',
        "\x63" => 'processPlayers',
        "\x72" => 'processRules',
    ];

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
            $result->addPlayer('score', $buffer->readInt32Signed());
        }

        return $result->fetch();
    }
}
