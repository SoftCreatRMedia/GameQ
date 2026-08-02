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

use GameQ\Server;

/**
 * Tests for games that use the Source query protocol without custom parsing.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class SourceAliases extends Base
{
    /**
     * @return iterable<string, array{string, int, int, string}>
     */
    public static function aliases(): iterable
    {
        yield 'American Truck Simulator' => ['ats', 27015, 27016, 'American Truck Simulator'];
        yield 'Euro Truck Simulator 2' => ['ets2', 27015, 27016, 'Euro Truck Simulator 2'];
        yield 'Counter-Strike 2' => ['cs2', 27015, 27015, 'Counter-Strike 2'];
        yield 'Enshrouded' => ['enshrouded', 15636, 15637, 'Enshrouded'];
        yield 'Nova-Life' => ['novalife', 7777, 27015, 'Nova-Life: Amboise'];
        yield 'Arma Reforger' => ['armareforger', 2001, 17777, 'Arma Reforger'];
        yield 'Wreckfest' => ['wreckfest', 33540, 27016, 'Wreckfest'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('aliases')]
    public function testSourceAlias(string $type, int $clientPort, int $queryPort, string $name): void
    {
        $server = new Server([
            Server::SERVER_TYPE => $type,
            Server::SERVER_HOST => "127.0.0.1:$clientPort",
        ]);

        self::assertInstanceOf(\GameQ\Protocols\Source::class, $server->protocolInstance());
        self::assertSame($type, (string) $server->protocolInstance());
        self::assertSame($name, $server->protocolInstance()->nameLong());
        self::assertSame($queryPort, $server->portQuery());
    }
}
