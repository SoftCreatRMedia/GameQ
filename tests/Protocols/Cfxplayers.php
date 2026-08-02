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

namespace GameQ\Tests\Protocols;

use GameQ\Exception\ProtocolException;
use GameQ\Tests\TestBase;

class Cfxplayers extends TestBase
{
    public function testArrayResponseIsParsed(): void
    {
        $protocol = new \GameQ\Protocols\Cfxplayers();
        $protocol->packetResponse([
            "HTTP/1.0 200 OK\r\nContent-Type: application/json\r\n\r\n"
            . '[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"}]',
        ]);

        self::assertSame([
            'players' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
        ], $protocol->processResponse());
    }

    public function testObjectResponseIsRejected(): void
    {
        $protocol = new \GameQ\Protocols\Cfxplayers();
        $protocol->packetResponse(['{"id":1,"name":"Alice"}']);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains('must be a list');

        $protocol->processResponse();
    }
}
