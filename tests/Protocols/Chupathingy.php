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
use GameQ\GameQ;
use GameQ\Http\HttpException;
use GameQ\Http\Response;
use GameQ\Protocol;
use GameQ\Protocols\Chupathingy as ChupathingyProtocol;
use GameQ\Server;
use GameQ\Tests\Fixtures\HttpClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class Chupathingy extends TestCase
{
    private const INVITE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    /** @return list<string> */
    private static function fields(): array
    {
        return [self::INVITE, 'Überlebende 日本', 'bloodgulch@ce', '2', '2', '8', '1', '11', '5', '50', '1',
            '0:Alice|1:Björk', 'EU-DE', 'pc_linux', 'dedicated', 'delta', 'pc_linux:2', 'Team Slayer',
            '0000941f', 'Abyss', 'Halo CE+', 'CE+'];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function query(string $body, array $options = []): array
    {
        $gameQ = new GameQ();
        $gameQ->addServer(['id' => 'test', 'type' => 'chupathingy',
            'options' => $options + ['server_invite' => self::INVITE, 'directory_response' => $body]]);

        return $gameQ->process()['test'];
    }

    public function testFullDirectoryRecordAndHonestVirtualEndpoint(): void
    {
        $result = $this->query(implode("\t", self::fields()));
        self::assertTrue($result['gq_online']);
        self::assertSame('Überlebende 日本', $result['gq_hostname']);
        self::assertSame('bloodgulch@ce', $result['gq_mapname']);
        self::assertSame(2, $result['gq_numplayers']);
        self::assertSame(8, $result['gq_maxplayers']);
        self::assertTrue($result['gq_dedicated']);
        self::assertSame('Slayer', $result['gq_gametype']);
        self::assertSame(11, $result['version']);
        self::assertSame('Team Slayer', $result['gametype']);
        self::assertSame('Abyss', $result['map_title']);
        self::assertSame('Halo CE+', $result['map_pack']);
        self::assertSame('CE+', $result['map_pack_short']);
        self::assertSame('halo://join/' . self::INVITE, $result['gq_joinlink']);
        self::assertNull($result['gq_address']);
        self::assertNull($result['gq_port_client']);
        self::assertNull($result['gq_port_query']);
        self::assertArrayNotHasKey('gq_password', $result);
        self::assertIsArray($result['players']);
        self::assertIsArray($result['players'][0]);
        self::assertSame('Alice', $result['players'][0]['gq_name']);
        self::assertSame(0, $result['players'][0]['gq_team']);
        self::assertArrayNotHasKey('gq_score', $result['players'][0]);
        self::assertSame([], $result['teams']);
    }

    public function testSanitizedRealDirectoryCapture(): void
    {
        $body = file_get_contents(__DIR__ . '/Providers/Chupathingy/captured-directory.txt');
        self::assertIsString($body);
        $lines = explode("\n", rtrim($body, "\r\n"));
        self::assertCount(40, $lines);

        foreach ($lines as $line) {
            $fields = explode("\t", $line);
            $result = $this->query($body, ['server_invite' => $fields[0]]);
            self::assertTrue($result['gq_online']);
            self::assertSame($fields[1], $result['gq_hostname']);
            self::assertSame((int) $fields[4], $result['gq_numplayers']);
            self::assertSame((int) $fields[5], $result['gq_maxplayers']);
            self::assertNull($result['gq_address']);
        }
    }

    public function testMinimalAndPartialOptionalGroups(): void
    {
        foreach ([8, 9, 10, 11, 12, 13, 17, 19, 22] as $length) {
            $result = $this->query(implode("\t", array_slice(self::fields(), 0, $length)));
            self::assertTrue($result['gq_online']);
            self::assertSame(2, $result['gq_numplayers']);
        }
    }

    public function testUnknownModeAndHostingAreNotInvented(): void
    {
        $fields = array_slice(self::fields(), 0, 8);
        $fields[3] = '0';
        $result = $this->query(implode("\t", $fields));
        self::assertTrue($result['gq_online']);
        self::assertNull($result['gq_gametype']);
        self::assertNull($result['gq_dedicated']);
    }

    public function testInviteNormalizationAndVirtualServerId(): void
    {
        $server = new Server(['type' => 'chupathingy', 'options' => ['server_invite' => 'halo://join/' . strtoupper(self::INVITE)]]);
        self::assertSame('chupathingy:' . self::INVITE, $server->id());
        self::assertSame('halo://join/' . self::INVITE, $server->getJoinLink());
        self::assertNull($server->ip);
        self::assertNull($server->port_client);
        self::assertNull($server->port_query);
        self::assertSame(Protocol::STATE_BETA, $server->protocolInstance()->state());
    }

    public function testMissingAndDuplicateTargetsAreOffline(): void
    {
        self::assertFalse($this->query('')['gq_online']);
        $line = implode("\t", self::fields());
        self::assertFalse($this->query($line, ['server_invite' => str_repeat('b', 64)])['gq_online']);
        self::assertFalse($this->query($line . "\n" . $line)['gq_online']);
        self::assertFalse($this->query($line . "\n" . strtoupper(self::INVITE) . "\tbad")['gq_online']);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidInvites(): iterable
    {
        foreach (
            [null, 123, '', str_repeat('a', 44), str_repeat('a', 63), str_repeat('a', 65), str_repeat('g', 64),
                'https://example.test/' . self::INVITE, 'halo://join/' . self::INVITE . '?x=1', self::INVITE . "\n"] as $i => $value
        ) {
            yield (string) $i => [$value];
        }
    }

    #[DataProvider('invalidInvites')]
    public function testInvalidInviteIsRejectedBeforeNetworking(mixed $value): void
    {
        $this->expectException(ProtocolException::class);
        new ChupathingyProtocol(['server_invite' => $value]);
    }

    public function testMetadataCanBeInspectedWithoutCreatingATarget(): void
    {
        $protocol = new ChupathingyProtocol();
        self::assertSame('chupathingy', $protocol->name());
        self::assertSame('name', $protocol->getNormalize()['general']['hostname']);
        self::assertNull($protocol->joinLink());
    }

    public function testExplicitServerIdDoesNotBypassRequiredInvite(): void
    {
        $this->expectException(ProtocolException::class);
        new Server(['type' => 'chupathingy', 'id' => 'custom-target']);
    }

    public function testUnconfiguredProtocolCannotStartANetworkQuery(): void
    {
        $protocol = new ChupathingyProtocol();
        $server = new Server(['type' => 'source', 'host' => '127.0.0.1:27015']);
        $this->expectException(ProtocolException::class);
        $protocol->beforeSend($server);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function misleadingTargets(): iterable
    {
        yield 'host' => [['host' => '127.0.0.1:443', 'options' => ['server_invite' => self::INVITE]]];
        yield 'query port' => [['options' => ['server_invite' => self::INVITE, 'query_port' => 443]]];
        yield 'invalid options' => [['options' => null]];
    }

    /** @param array<string, mixed> $info */
    #[DataProvider('misleadingTargets')]
    public function testVirtualTargetsRejectFakeAddresses(array $info): void
    {
        $this->expectException(ServerException::class);
        new Server(['type' => 'chupathingy'] + $info);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidRecords(): iterable
    {
        $original = self::fields();

        foreach ([7, 23] as $count) {
            yield 'field count ' . $count => [implode("\t", array_pad(array_slice($original, 0, $count), $count, ''))];
        }

        foreach (
            [1 => ['', str_repeat('N', 257), "bad\0name", "bad\xFF"], 2 => [str_repeat('m', 513)],
                3 => ['7', '-1'], 4 => ['129', '9', '2.0'], 5 => ['0'], 6 => ['true', '2'], 7 => ['-1'],
                8 => ['-1', '2147483648'], 10 => ['2'], 11 => ['0:A|1:B|0:C', 'bad', '0:', '0:' . str_repeat('n', 257)],
                14 => ['fake'], 15 => ['fake'], 16 => ['pc_linux:129', 'bad', 'pc_linux:-1'], 18 => ['xyz']] as $offset => $values
        ) {
            foreach ($values as $index => $value) {
                $fields = $original;
                $fields[$offset] = $value;
                yield $offset . '/' . $index => [implode("\t", $fields)];
            }
        }

        yield 'too many records' => [implode("\n", array_fill(0, 65, implode("\t", $original)))];
        yield 'too many bytes' => [str_repeat('a', 32769)];
    }

    #[DataProvider('invalidRecords')]
    public function testMalformedRecordsAreOffline(string $body): void
    {
        self::assertFalse($this->query($body)['gq_online']);
    }

    public function testFixedHttpEndpointLimitsAndClientScopedCache(): void
    {
        $body = implode("\t", self::fields());
        $first = new HttpClient([new Response(200, $body), new Response(200, '')]);
        $gameQ = new GameQ();
        $gameQ->setHttpClient($first)->addServer(['id' => 'test', 'type' => 'chupathingy',
            'options' => ['server_invite' => self::INVITE, 'http_timeout' => 999, 'directory_url' => 'http://127.0.0.1/']]);
        self::assertTrue($gameQ->process()['test']['gq_online']);
        self::assertTrue($gameQ->process()['test']['gq_online']);
        self::assertCount(1, $first->requests);
        self::assertSame('https://stats.chupathingy.org/v1/games.txt?fields=22', $first->requests[0]->url);
        self::assertSame(['Accept' => 'text/plain'], $first->requests[0]->headers);
        self::assertSame(32768, $first->requests[0]->maxResponseBytes);
        self::assertSame(30, $first->requests[0]->timeout);
        $second = new HttpClient([new Response(200, '')]);
        $gameQ->setHttpClient($second);
        self::assertFalse($gameQ->process()['test']['gq_online']);
        self::assertCount(1, $second->requests);
    }

    public function testFailedHttpRequestsAndOversizedBodiesAreOffline(): void
    {
        foreach (
            [new Response(301, ''), new Response(404, ''), new Response(200, str_repeat('a', 32769)),
                new HttpException('timeout'), new HttpException('TLS failure')] as $response
        ) {
            $client = new HttpClient([$response]);
            $gameQ = new GameQ();
            $gameQ->setHttpClient($client)->addServer(['id' => 'test', 'type' => 'chupathingy',
                'options' => ['server_invite' => self::INVITE, 'directory_cache_ttl' => 0]]);
            self::assertFalse($gameQ->process()['test']['gq_online']);
        }
    }

    public function testAReusedProtocolClearsEarlierResults(): void
    {
        $protocol = new ChupathingyProtocol(['server_invite' => self::INVITE, 'directory_cache_ttl' => 0]);
        $protocol->setHttpClient(new HttpClient([new Response(200, implode("\t", self::fields())), new Response(200, '')]));
        $server = new Server(['type' => 'chupathingy', 'options' => ['server_invite' => self::INVITE]]);
        $protocol->beforeSend($server);
        self::assertNotSame([], $protocol->processResponse());
        $protocol->beforeSend($server);
        self::assertSame([], $protocol->processResponse());
    }
}
