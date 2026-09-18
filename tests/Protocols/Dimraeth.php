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

use GameQ\Exception\ServerException;
use GameQ\Server;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionException;
use RuntimeException;

/**
 * Test class for Dimraeth servers running WaygateServer.
 */
class Dimraeth extends Base
{
    /**
     * @return list<array{list<string>, non-empty-array<string, array<string, mixed>>}>
     *
     * @throws JsonException
     */
    public static function loadHexData(): array
    {
        $providers = [];
        $responsePaths = \glob(__DIR__ . '/Providers/Dimraeth/*_response.hex');

        if ($responsePaths === false) {
            throw new RuntimeException('Unable to enumerate Dimraeth response fixtures.');
        }

        foreach ($responsePaths as $responsePath) {
            $resultPath = \substr($responsePath, 0, -\strlen('_response.hex')) . '_result.json';
            $responseContents = \file($responsePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $resultContents = \file_get_contents($resultPath);

            if ($responseContents === false || $resultContents === false) {
                throw new RuntimeException("Unable to read Dimraeth fixture '$responsePath'.");
            }

            $responses = [];

            foreach ($responseContents as $hex) {
                $response = \hex2bin($hex);

                if ($response === false) {
                    throw new RuntimeException("Invalid hexadecimal response in '$responsePath'.");
                }

                $responses[] = $response;
            }

            $result = \json_decode($resultContents, true, 512, JSON_THROW_ON_ERROR);

            if (!\is_array($result) || $result === []) {
                throw new RuntimeException("Invalid result fixture '$resultPath'.");
            }

            /** @var non-empty-array<string, array<string, mixed>> $result */
            $providers[] = [$responses, $result];
        }

        return $providers;
    }

    /**
     * @param list<string> $responses
     * @param non-empty-array<string, array<string, mixed>> $result
     *
     * @throws ServerException
     * @throws ReflectionException
     */
    #[DataProvider('loadHexData')]
    public function testResponses(array $responses, array $result): void
    {
        $server = self::firstServerKey($result);

        self::assertEquals(
            $result[$server],
            $this->queryTest($server, 'dimraeth', $responses),
        );
    }

    public function testProtocolSettings(): void
    {
        $protocol = new \GameQ\Protocols\Dimraeth();

        self::assertSame('dimraeth', $protocol->name());
        self::assertSame('Dimraeth (WaygateServer)', $protocol->nameLong());
        self::assertSame(1, $protocol->portDiff());
        self::assertSame(15570, $protocol->findQueryPort(15569));
        self::assertNull($protocol->joinLink());
    }

    /**
     * @throws ReflectionException
     * @throws JsonException
     * @throws ServerException
     */
    public function testExplicitQueryPortOverridesDerivedPort(): void
    {
        $responses = self::loadHexData()[0][0];
        $result = $this->queryTest(
            '127.0.0.1:15569',
            'dimraeth',
            $responses,
            false,
            [Server::SERVER_OPTIONS_QUERY_PORT => 16000],
        );

        self::assertSame(16000, $result['gq_port_query']);
        self::assertSame('', $result['gq_joinlink']);
    }
}
