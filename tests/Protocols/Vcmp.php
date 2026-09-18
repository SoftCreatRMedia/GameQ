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

use GameQ\Exception\ServerException;
use ReflectionException;

/**
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Vcmp extends Base
{
    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testInformationAndPlayerResponses(): void
    {
        $serverCode = "\x7F\x00\x00\x01" . pack('v', 5192);
        $information = 'MP04' . $serverCode . 'i'
            . str_pad('0.4.7.1', 12, "\0")
            . pack('Cvv', 1, 2, 100)
            . pack('V', 11) . 'Vice Server'
            . pack('V', 10) . 'Deathmatch'
            . pack('V', 9) . 'Vice City';
        $players = 'MP04' . $serverCode . 'c' . pack('v', 2) . "\x05Alice\x03Bob";

        $result = $this->queryTest('127.0.0.1:5192', 'vcmp', [$information, $players]);

        self::assertTrue($result['gq_online']);
        self::assertSame('0.4.7.1', $result['version']);
        self::assertSame('Vice Server', $result['servername']);
        self::assertSame('Deathmatch', $result['gametype']);
        self::assertSame('Vice City', $result['mapname']);
        self::assertIsArray($result['players']);
        self::assertSame(['name' => 'Alice'], $result['players'][0]);
        self::assertSame(['name' => 'Bob'], $result['players'][1]);
    }
}
