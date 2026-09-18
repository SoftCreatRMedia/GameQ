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

namespace GameQ\Tests\Protocols;

use GameQ\Exception\ProtocolException;
use GameQ\Exception\ServerException;
use GameQ\Protocol;
use ReflectionException;

/**
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Srb2 extends Base
{
    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testCurrentServerInfoPacket(): void
    {
        $body = "\0\0\x0D\0"
            . "\xFF\x05"
            . str_pad('SRB2', 16, "\0")
            . pack('C5', 202, 15, 4, 32, 0)
            . str_pad('Race', 24, "\0")
            . pack('C4', 1, 0, 0x60, 2)
            . pack('V2', 1234, 567)
            . str_pad('Greenflower Zone', 32, "\0")
            . str_pad('MAP01', 8, "\0")
            . str_pad('GREENFLOWER', 33, "\0")
            . str_repeat("\x01", 16)
            . "\x01\x01";
        $checksum = 0x1234567;

        for ($i = 0, $length = strlen($body); $i < $length; ++$i) {
            $checksum += ord($body[$i]) * ($i + 1);
        }

        $playerInfo = str_repeat("\0", 6) . "\x0E\0";
        $result = $this->queryTest('127.0.0.1:5029', 'srb2', [
            pack('V', $checksum) . $body,
            $playerInfo,
        ]);

        self::assertTrue($result['gq_online']);
        self::assertSame('2.2.15', $result['version']);
        self::assertSame('Greenflower Zone', $result['name']);
        self::assertSame('MAP01', $result['map_name']);
        self::assertSame('GREENFLOWER', $result['map']);
        self::assertTrue($result['dedicated']);
        self::assertTrue($result['lots_of_addons']);
        self::assertSame(1, $result['act']);
        self::assertTrue($result['zone']);
    }

    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testInvalidChecksumIsRejected(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains('checksum');
        $this->queryTest('127.0.0.1:5029', 'srb2', [str_repeat("\0", 6) . "\x0D" . str_repeat("\0", 151)], true);
    }

    /**
     * @throws ProtocolException
     */
    public function testRequestMatchesCurrentOfficialAskInfoLayout(): void
    {
        $protocol = new \GameQ\Protocols\Srb2();
        $packet = $protocol->getPacket(Protocol::PACKET_STATUS);

        self::assertIsString($packet);
        self::assertSame("\0\0\x0C\0\xCA\0\0\0\0", substr($packet, 4));
    }
}
