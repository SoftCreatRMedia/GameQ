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
use ReflectionException;

/**
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Mindustry extends Base
{
    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testDiscoveryResponse(): void
    {
        $response = self::string('Test Server')
            . self::string('Ground Zero')
            . pack('N3', 12, 24, 151)
            . self::string('official')
            . pack('CN', 2, 30)
            . self::string('Attack server')
            . self::string('custom attack')
            . pack('n', 6567);

        $result = $this->queryTest('127.0.0.1:6567', 'mindustry', [$response]);

        self::assertTrue($result['gq_online']);
        self::assertSame('Test Server', $result['name']);
        self::assertSame('Ground Zero', $result['map']);
        self::assertSame(12, $result['num_players']);
        self::assertSame(30, $result['max_players']);
        self::assertSame('151', $result['version']);
        self::assertSame('attack', $result['gamemode']);
        self::assertSame(6567, $result['game_port']);
    }

    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testTruncatedStringIsRejected(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains('string exceeds');
        $this->queryTest('127.0.0.1:6567', 'mindustry', ["\x05no"], true);
    }

    private static function string(string $value): string
    {
        return chr(strlen($value)) . $value;
    }
}
