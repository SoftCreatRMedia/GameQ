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
use GameQ\Server;

/**
 * CryoFall protocol tests.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Cryofall extends Base
{
    public function testStatefulQuerySequence(): void
    {
        $protocol = new \GameQ\Protocols\Cryofall();
        $protocol->beforeSend(new Server([
            Server::SERVER_TYPE => 'cryofall',
            Server::SERVER_HOST => '127.0.0.1:6000',
        ]));

        $protocol->packetResponse(['acknowledged']);
        self::assertSame([
            "\x0C\x0A\x00\x01\x00\x00\x02\x00\x05\x60\x02\xE8\x03\x07\x00\x00\x06\x5F\x02\x20\x4E\x01",
        ], $protocol->getFollowUpPackets());

        $protocol->appendPacketResponse(['negotiated']);
        self::assertSame([
            "\x01\x00\x00\x02\x00\x05\x60\x02\xE8\x03",
        ], $protocol->getFollowUpPackets());

        $protocol->appendPacketResponse(['short']);
        self::assertSame([
            "\x00\x06\x61\x02\x20\x4E\x02",
        ], $protocol->getFollowUpPackets());

        $protocol->appendPacketResponse(['final response']);
        self::assertSame([], $protocol->getFollowUpPackets());
    }

    public function testStatusResponse(): void
    {
        $guid = hex2bin('00112233445566778899AABBCCDDEEFF');
        self::assertIsString($guid);

        $response = str_repeat("\x00", 11)
            . self::string16('CryoFall Test')
            . "\x00\x00"
            . self::string16('R33')
            . pack('vv', 7, 40)
            . "\x00\x00\x00"
            . self::string16('A test server')
            . pack('v', 8)
            . strrev($guid)
            . str_repeat("\x00", 8)
            . "\x01"
            . self::string16('Example Mod')
            . self::string16('1.2.3')
            . self::string16('Example description')
            . "\x00\x00"
            . "\x01"
            . self::string16('PvE')
            . "\x01\x00";

        $protocol = new \GameQ\Protocols\Cryofall();
        $protocol->packetResponse(['handshake', 'negotiation', $response]);

        self::assertSame([
            'dedicated' => true,
            'map' => 'CryoFall',
            'hostname' => 'CryoFall Test',
            'version' => 'R33',
            'num_players' => 7,
            'max_players' => 40,
            'description' => 'A test server',
            'guid' => '00112233445566778899AABBCCDDEEFF',
            'mods' => [[
                'name' => 'Example Mod',
                'version' => '1.2.3',
                'description' => 'Example description',
            ]],
            'options' => ['PvE'],
            'community_server' => true,
            'no_client_mods' => false,
        ], $protocol->processResponse());
    }

    public function testInvalidMetadataLengthIsRejected(): void
    {
        $response = str_repeat("\x00", 11)
            . self::string16('CryoFall Test')
            . "\x00\x00"
            . self::string16('R33')
            . pack('vv', 7, 40)
            . "\x00\x00\x00"
            . self::string16('A test server')
            . pack('v', 7);
        $protocol = new \GameQ\Protocols\Cryofall();
        $protocol->packetResponse(['handshake', 'negotiation', $response]);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains('CryoFall server metadata length is invalid.');

        $protocol->processResponse();
    }

    private static function string16(string $value): string
    {
        return pack('v', strlen($value)) . $value;
    }
}
