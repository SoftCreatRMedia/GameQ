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
class Openmp extends Base
{
    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testCompactPlayerResponse(): void
    {
        $serverCode = "\x7F\x00\x00\x01" . pack('v', 7777);
        $response = 'SAMP' . $serverCode . 'c' . pack('v', 2)
            . "\x05Alice" . pack('V', 42)
            . "\x03Bob" . pack('V', 0xFFFFFFF9);

        $result = $this->queryTest('127.0.0.1:7777', 'openmp', [$response]);

        self::assertTrue($result['gq_online']);
        self::assertSame(2, $result['num_players']);
        self::assertIsArray($result['players']);
        self::assertIsArray($result['players'][0]);
        self::assertIsArray($result['players'][1]);
        self::assertSame('Alice', $result['players'][0]['name']);
        self::assertSame(42, $result['players'][0]['score']);
        self::assertSame(-7, $result['players'][1]['score']);
    }
}
