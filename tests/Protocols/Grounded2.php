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
use GameQ\Protocol;
use GameQ\Server;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionException;
use RuntimeException;

/**
 * Tests for Grounded 2 servers running LanternServer.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Grounded2 extends Base
{
    /**
     * @return list<array{list<string>, non-empty-array<string, array<string, mixed>>}>
     *
     * @throws JsonException
     */
    public static function loadHexData(): array
    {
        $providers = [];
        $responsePaths = \glob(__DIR__ . '/Providers/Grounded2/*_response.hex');

        if ($responsePaths === false) {
            throw new RuntimeException('Unable to enumerate Grounded 2 response fixtures.');
        }

        foreach ($responsePaths as $responsePath) {
            $resultPath = \substr($responsePath, 0, -\strlen('_response.hex')) . '_result.json';
            $responseContents = \file($responsePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $resultContents = \file_get_contents($resultPath);

            if ($responseContents === false || $resultContents === false) {
                throw new RuntimeException("Unable to read Grounded 2 fixture '$responsePath'.");
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
     * @throws ReflectionException
     * @throws ServerException
     */
    #[DataProvider('loadHexData')]
    public function testResponses(array $responses, array $result): void
    {
        $server = self::firstServerKey($result);
        $expected = $result[$server];
        $actual = $this->queryTest($server, 'grounded2', $responses);

        ksort($expected);
        ksort($actual);

        self::assertSame($expected, $actual);
    }

    /**
     * @throws ServerException
     */
    public function testProtocolMetadataAndPorts(): void
    {
        $server = new Server([
            Server::SERVER_TYPE => 'grounded2',
            Server::SERVER_HOST => '192.0.2.10:7777',
        ]);
        $protocol = $server->protocolInstance();

        self::assertSame('grounded2', (string) $protocol);
        self::assertSame('Grounded 2 (requires LanternServer)', $protocol->nameLong());
        self::assertSame(Protocol::STATE_BETA, $protocol->state());
        self::assertSame(2, $protocol->portDiff());
        self::assertSame(7779, $server->portQuery());
        self::assertSame(Protocol::TRANSPORT_UDP, $protocol->transport());
        self::assertNull($protocol->joinLink());
        self::assertNull($server->getJoinLink());

        $overriddenServer = new Server([
            Server::SERVER_TYPE => 'grounded2',
            Server::SERVER_HOST => '192.0.2.10:7777',
            Server::SERVER_OPTIONS => [Server::SERVER_OPTIONS_QUERY_PORT => 28015],
        ]);

        self::assertSame(28015, $overriddenServer->portQuery());
    }

    /**
     * @throws ReflectionException
     * @throws ServerException
     */
    public function testMissingResponseIsOffline(): void
    {
        self::assertFalse($this->queryTest('192.0.2.10:7777', 'grounded2', [])['gq_online']);
    }
}
