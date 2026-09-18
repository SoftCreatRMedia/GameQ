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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace GameQ\Tests\Protocols;

use GameQ\Exception\ProtocolException;
use GameQ\Exception\ServerException;
use GameQ\Protocols\Dragonwilds as DragonwildsProtocol;
use GameQ\Server;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionException;

/**
 * Tests for the RuneScape: Dragonwilds EOS directory protocol.
 */
class Dragonwilds extends Base
{
    /**
     * @param list<string> $responses
     * @param non-empty-array<string, array<string, mixed>> $result
     *
     * @throws ServerException
     * @throws ReflectionException
     */
    #[DataProvider('loadData')]
    public function testResponses(array $responses, array $result): void
    {
        $server = self::firstServerKey($result);

        self::assertSame(
            $result[$server],
            $this->queryTest($server, 'dragonwilds', $responses, false, ['skip_http_requests' => true]),
        );
    }

    /**
     * @throws ServerException
     */
    public function testProtocolMetadataAndDefaultPortBehavior(): void
    {
        $server = new Server([
            'type' => 'dragonwilds',
            'host' => '192.0.2.10:7777',
            'options' => ['skip_http_requests' => true],
        ]);
        $protocol = $server->protocolInstance();

        self::assertSame('dragonwilds', (string) $protocol);
        self::assertSame('RuneScape: Dragonwilds', $protocol->nameLong());
        self::assertSame(7777, $server->portQuery());
        self::assertSame('tcp', $protocol->transport());
        self::assertNull($protocol->joinLink());
    }

    /**
     * @throws JsonException
     * @throws ProtocolException
     * @throws ServerException
     */
    public function testSameAddressDifferentPortsSelectsOnlyTheExactPort(): void
    {
        $wrongPort = $this->session(7778, ['VN_s' => 'Wrong server']);
        $exactPort = $this->session(7777, ['VN_s' => 'Exact server']);

        self::assertSame('Exact server', $this->parseSessions([$wrongPort, $exactPort])['hostname']);
    }

    /**
     * @throws JsonException
     * @throws ProtocolException
     * @throws ServerException
     */
    public function testInactiveAndNonListeningSessionsAreIgnored(): void
    {
        $notStarted = $this->session(7777, ['VN_s' => 'Not started'], ['started' => false]);
        $notReady = $this->session(7777, ['VN_s' => 'Not ready', 'X0_l' => 0]);
        $notListening = $this->session(7777, [
            'VN_s' => 'Not listening',
            '__EOS_BLISTENING_b' => false,
        ]);
        $active = $this->session(7777, ['VN_s' => 'Active']);

        self::assertSame(
            'Active',
            $this->parseSessions([$notStarted, $notReady, $notListening, $active])['hostname'],
        );
    }

    /**
     * @throws ProtocolException
     * @throws JsonException
     * @throws ServerException
     */
    public function testNewestDuplicateForTheSameStableServerIsSelected(): void
    {
        $old = $this->session(7777, ['VN_s' => 'Stale'], ['lastUpdated' => '2026-09-18T08:00:00Z']);
        $new = $this->session(7777, ['VN_s' => 'Current'], ['lastUpdated' => '2026-09-18T08:01:00Z']);

        self::assertSame('Current', $this->parseSessions([$old, $new])['hostname']);
    }

    /**
     * @throws JsonException
     * @throws ServerException
     */
    public function testDifferentStableServerIdentifiersAreAmbiguous(): void
    {
        $first = $this->session(7777, ['XX_s' => 'server-one']);
        $second = $this->session(7777, ['XX_s' => 'server-two']);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains('ambiguous');
        $this->parseSessions([$first, $second]);
    }

    /**
     * @throws JsonException
     * @throws ServerException
     */
    public function testDuplicateWithoutComparableTimestampsIsAmbiguous(): void
    {
        $first = $this->session(7777, [], ['lastUpdated' => null]);
        $second = $this->session();

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains('ambiguous');
        $this->parseSessions([$first, $second]);
    }

    /**
     * @throws JsonException
     * @throws ServerException
     */
    public function testNoExactPortMatchFails(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains('address and port');
        $this->parseSessions([$this->session(7778)]);
    }

    /**
     * @param 'attributes'|'session'|'settings' $scope
     * @param string $key
     * @param mixed $value
     *
     * @throws JsonException
     * @throws ProtocolException
     * @throws ServerException
     */
    #[DataProvider('invalidTypedFieldProvider')]
    public function testInvalidTypedFieldsFail(string $scope, string $key, mixed $value): void
    {
        $session = $this->session();

        if ($scope === 'attributes' || $scope === 'settings') {
            self::assertIsArray($session[$scope]);
            $session[$scope][$key] = $value;
        } else {
            $session[$key] = $value;
        }

        $this->expectException(ProtocolException::class);
        $this->parseSessions([$session]);
    }

    /**
     * @return list<array{'attributes'|'session'|'settings', string, mixed}>
     */
    public static function invalidTypedFieldProvider(): array
    {
        return [
            ['attributes', 'VN_s', 123],
            ['attributes', 'XS_s', null],
            ['attributes', 'MAPNAME_s', false],
            ['attributes', 'XB_l', '240163'],
            ['attributes', 'XP_l', []],
            ['attributes', 'XP_l', 'not-a-number'],
            ['session', 'totalPlayers', '0'],
            ['settings', 'maxPublicPlayers', 6.5],
        ];
    }

    /**
     * @throws JsonException
     * @throws ServerException
     */
    public function testPlayerCountAboveCapacityFails(): void
    {
        $session = $this->session();
        $session['totalPlayers'] = 7;

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains('above its capacity');
        $this->parseSessions([$session]);
    }

    /**
     * @throws ServerException
     */
    public function testMalformedAndTruncatedJsonFail(): void
    {
        $protocol = $this->preparedProtocol();
        $protocol->packetResponse(['{}', '{"sessions":']);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessageContains('invalid JSON');
        $protocol->processResponse();
    }

    /**
     * @throws JsonException
     * @throws ProtocolException
     * @throws ServerException
     */
    public function testSensitiveDirectoryFieldsAreNotReturned(): void
    {
        $session = $this->session(7777, [
            'OI_s' => 'private-owner-id',
            'XI_s' => 'private-account-id',
            'XW_s' => 'private-owner-name',
            'XZ_s' => 'private-invite-value',
            'XP_l' => 123456789,
        ], [
            'id' => 'private-session-id',
            'owner' => 'private-owner',
            'ownerPlatformId' => 'private-platform-id',
            'publicPlayers' => ['private-player-id'],
        ]);
        $result = $this->parseSessions([$session]);

        self::assertTrue($result['password']);

        foreach (['OI_s', 'XI_s', 'XW_s', 'XZ_s', 'XP_l', 'id', 'owner', 'ownerPlatformId', 'publicPlayers'] as $key) {
            self::assertArrayNotHasKey($key, $result);
        }

        self::assertStringNotContainsString('private-', json_encode($result, JSON_THROW_ON_ERROR));
    }

    /**
     * @throws JsonException
     * @throws ProtocolException
     * @throws ServerException
     */
    public function testVersionMismatchBuildRemainsVisibleAsVersion(): void
    {
        $result = $this->parseSessions([$this->session(7777, ['XB_l' => 232224])]);

        self::assertSame('232224', $result['version']);
    }

    /**
     * @param int $statusCode
     */
    #[DataProvider('httpFailureStatusProvider')]
    public function testHttpFailureStatusesAreRejected(int $statusCode): void
    {
        $protocol = new class extends DragonwildsProtocol {
            /** @return array<string, mixed>|null */
            public function decodeForTest(bool $success, int $statusCode, string $response): ?array
            {
                return $this->decodeHttpResponse($success, $statusCode, $response);
            }
        };

        self::assertNull($protocol->decodeForTest(true, $statusCode, '{}'));
    }

    /** @return list<array{int}> */
    public static function httpFailureStatusProvider(): array
    {
        return [[400], [401], [403], [404], [429], [500], [502], [503]];
    }

    public function testMalformedHttpJsonAndTransportFailuresAreRejected(): void
    {
        $protocol = new class extends DragonwildsProtocol {
            /** @return array<string, mixed>|null */
            public function decodeForTest(bool $success, int $statusCode, string $response): ?array
            {
                return $this->decodeHttpResponse($success, $statusCode, $response);
            }
        };

        self::assertNull($protocol->decodeForTest(true, 200, '{'));
        self::assertNull($protocol->decodeForTest(false, 0, ''));
        self::assertSame(['sessions' => []], $protocol->decodeForTest(true, 200, '{"sessions":[]}'));
    }

    public function testHttpSecurityLimitsAreExplicit(): void
    {
        $protocol = new class (['http_timeout' => 999]) extends DragonwildsProtocol {
            /** @return array<int, bool|int> */
            public function securityOptionsForTest(): array
            {
                return $this->httpSecurityOptions();
            }

            public function timeoutForTest(): int
            {
                return $this->httpTimeout();
            }
        };
        $options = $protocol->securityOptionsForTest();

        self::assertSame(CURLPROTO_HTTPS, $options[CURLOPT_PROTOCOLS]);
        self::assertSame(CURLPROTO_HTTPS, $options[CURLOPT_REDIR_PROTOCOLS]);
        self::assertFalse($options[CURLOPT_FOLLOWLOCATION]);
        self::assertSame(0, $options[CURLOPT_MAXREDIRS]);
        self::assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        self::assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
        self::assertSame(8 * 1024 * 1024, $options[CURLOPT_MAXFILESIZE]);
        self::assertSame(30, $protocol->timeoutForTest());
    }

    public function testChunkedResponseLimitIsEnforced(): void
    {
        $protocol = new class extends DragonwildsProtocol {
            public function appendForTest(string &$response, string $chunk): int
            {
                return $this->appendResponseChunk($response, $chunk);
            }
        };
        $response = '';
        $maximum = 8 * 1024 * 1024;

        self::assertSame($maximum, $protocol->appendForTest($response, str_repeat('x', $maximum)));
        self::assertSame(0, $protocol->appendForTest($response, 'x'));
        self::assertSame($maximum, strlen($response));
    }

    /**
     * @throws ServerException
     */
    public function testAuthenticationIsRefreshedForEveryQuery(): void
    {
        $protocol = new class extends DragonwildsProtocol {
            /** @var list<string> */
            public array $tokens = [];

            private int $counter = 0;

            protected function authenticate(): string
            {
                return 'token-' . ++$this->counter;
            }

            protected function queryServers(string $authToken): array
            {
                $this->tokens[] = $authToken;

                return [];
            }
        };
        $server = new Server(['type' => 'dragonwilds', 'host' => '192.0.2.10:7777']);

        $protocol->beforeSend($server);
        $protocol->beforeSend($server);

        self::assertSame(['token-1', 'token-2'], $protocol->tokens);
        self::assertSame([], $protocol->packetResponse());
    }

    public function testOnlyFixedEpicEndpointsAreUsed(): void
    {
        $protocol = new class (['endpoint' => 'https://attacker.invalid/']) extends DragonwildsProtocol {
            /** @var list<string> */
            public array $urls = [];

            public function runRequestsForTest(): void
            {
                $token = $this->authenticate();

                if ($token !== null) {
                    $this->queryServers($token);
                }
            }

            protected function httpRequest(string $url, array $headers, string $postFields): array
            {
                $this->urls[] = $url;

                return str_ends_with($url, '/oauth/token')
                    ? ['access_token' => 'fixture-token']
                    : ['sessions' => []];
            }
        };

        $protocol->runRequestsForTest();

        self::assertSame([
            'https://api.epicgames.dev/auth/v1/oauth/token',
            'https://api.epicgames.dev/matchmaking/v1/dd10a15a04d945e2950e1e106884e17a/filter',
        ], $protocol->urls);
    }

    /**
     * @param array<string, mixed> $attributeOverrides
     * @param array<string, mixed> $sessionOverrides
     * @return array<string, mixed>
     */
    private function session(
        int $port = 7777,
        array $attributeOverrides = [],
        array $sessionOverrides = [],
    ): array {
        $attributes = array_replace([
            'ADDRESS_s' => '192.0.2.10',
            'ADDRESSBOUND_s' => '0.0.0.0:' . $port,
            'MAPNAME_s' => 'L_World-1234',
            'VN_s' => 'Dragonwilds test',
            'X0_l' => 1,
            'XB_l' => 240163,
            'XP_l' => -2,
            'XS_s' => 'TestWorld',
            'XX_s' => 'stable-server-guid',
            '__EOS_BLISTENING_b' => true,
        ], $attributeOverrides);

        return array_replace([
            'started' => true,
            'lastUpdated' => '2026-09-18T08:00:00Z',
            'totalPlayers' => 0,
            'attributes' => $attributes,
            'settings' => ['maxPublicPlayers' => 6],
        ], $sessionOverrides);
    }

    /**
     * @param list<array<string, mixed>> $sessions
     * @return array<string, mixed>
     *
     * @throws JsonException
     * @throws ProtocolException
     * @throws ServerException
     */
    private function parseSessions(array $sessions): array
    {
        $protocol = $this->preparedProtocol();
        $protocol->packetResponse([
            '{}',
            json_encode(['sessions' => $sessions], JSON_THROW_ON_ERROR),
        ]);

        return $protocol->processResponse();
    }

    /**
     * @throws ServerException
     */
    private function preparedProtocol(): DragonwildsProtocol
    {
        $protocol = new DragonwildsProtocol(['skip_http_requests' => true]);
        $protocol->beforeSend(new Server([
            'type' => 'dragonwilds',
            'host' => '192.0.2.10:7777',
            'options' => ['skip_http_requests' => true],
        ]));

        return $protocol;
    }
}
