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

use GameQ\Protocols\Cfx as CfxProtocol;
use GameQ\Tests\TestBase;
use ReflectionProperty;

/**
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Cfx extends TestBase
{
    public function testHttpDataSupplementsUdpResponse(): void
    {
        $protocol = new CfxProtocol();
        $protocol->packetResponse([
            "\xFF\xFF\xFF\xFFinfoResponse\n"
            . '\\hostname\\FiveM Test\\clients\\2\\sv_maxclients\\32\\gamename\\CitizenFX',
        ]);

        $playerList = new ReflectionProperty($protocol, 'playerList');
        $playerList->setValue($protocol, [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);
        $infoData = new ReflectionProperty($protocol, 'infoData');
        $infoData->setValue($protocol, [
            'version' => 'FXServer-1234',
            'vars' => [
                'Discord' => 'discord.gg/example',
                'locale' => 'de-DE',
            ],
        ]);

        $result = $protocol->processResponse();

        self::assertSame('FXServer-1234', $result['version']);
        self::assertSame('discord.gg/example', $result['fivem_discord']);
        self::assertSame('de-DE', $result['fivem_locale']);
        $players = $result['players'];
        self::assertIsArray($players);
        self::assertCount(2, $players);
        self::assertSame('32', $result['sv_maxclients']);
    }

    public function testHttpDataProvidesFallbackWhenUdpIsUnavailable(): void
    {
        $protocol = new CfxProtocol();
        $playerList = new ReflectionProperty($protocol, 'playerList');
        $playerList->setValue($protocol, [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);
        $infoData = new ReflectionProperty($protocol, 'infoData');
        $infoData->setValue($protocol, [
            'version' => 'FXServer-1234',
            'vars' => [
                'sv_maxClients' => '32',
                'locale' => 'de-DE',
            ],
        ]);

        $result = $protocol->processResponse();

        self::assertTrue($result['dedicated']);
        self::assertSame('CitizenFX', $result['gamename']);
        self::assertSame(2, $result['clients']);
        self::assertSame('32', $result['sv_maxclients']);
        self::assertSame('FXServer-1234', $result['version']);
        $players = $result['players'];
        self::assertIsArray($players);
        self::assertCount(2, $players);
    }
}
