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

/**
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Cfxinfo extends TestBase
{
    public function testObjectResponseIsParsed(): void
    {
        $protocol = new \GameQ\Protocols\Cfxinfo();
        $protocol->packetResponse([
            "HTTP/1.0 200 OK\r\nContent-Type: application/json\r\n\r\n"
            . '{"version":"FXServer-1234","vars":{"Discord":"discord.gg/example","locale":"de-DE"}}',
        ]);

        self::assertSame([
            'version' => 'FXServer-1234',
            'vars' => [
                'Discord' => 'discord.gg/example',
                'locale' => 'de-DE',
            ],
        ], $protocol->processResponse());
    }

    public function testListResponseIsRejected(): void
    {
        $protocol = new \GameQ\Protocols\Cfxinfo();
        $protocol->packetResponse(['[]']);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains('must be an object');

        $protocol->processResponse();
    }
}
