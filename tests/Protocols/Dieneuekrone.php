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

use GameQ\GameQ;
use GameQ\Http\HttpException;
use GameQ\Http\Response;
use GameQ\Protocols\Dieneuekrone as Directory;
use GameQ\Server;
use GameQ\Tests\Fixtures\HttpClient;
use GameQ\Tests\TestBase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Dieneuekrone extends TestBase
{
    /** @return array<string, mixed> */
    private function fixture(string $name): array
    {
        $document = json_decode(self::fixtureContents(__DIR__ . '/Providers/Dieneuekrone/' . $name . '.json'), true, 64, JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        $result = [];

        foreach ($document as $key => $value) {
            self::assertIsString($key);
            $result[$key] = $value;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function row(): array
    {
        $document = $this->fixture('controlled');
        self::assertIsArray($document['servers']);
        self::assertIsArray($document['servers'][0]);
        $row = [];

        foreach ($document['servers'][0] as $key => $value) {
            self::assertIsString($key);
            $row[$key] = $value;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function parse(array $document, string $host = '127.0.0.1:17777', array $options = [], int $clock = 1795816846): array
    {
        $protocol = new class (['directory_response' => $document] + $options, $clock) extends Directory {
            /** @param array<string, mixed> $options */
            public function __construct(array $options, private int $clock)
            {
                parent::__construct($options);
            }

            protected function now(): int
            {
                return $this->clock;
            }
        };
        $protocol->beforeSend(new Server(['type' => 'dieneuekrone', 'host' => $host]));

        return $protocol->processResponse();
    }

    public function testControlledServerAndPublicFieldWhitelist(): void
    {
        $document = $this->fixture('controlled');
        $row = $this->row();
        $row['secret_hash'] = 'must-not-leak';
        $row['host'] = 'private-storage-value';
        $row['api_key'] = 'must-not-leak';
        $document['servers'] = [$row];
        $result = $this->parse($document);
        self::assertSame('GameQ-Krone-Local-Test', $result['server_name']);
        self::assertSame('0.9.0.0', $result['version']);
        self::assertSame(0, $result['num_players']);
        self::assertSame(2, $result['max_games']);
        self::assertSame(0, $result['room_count']);
        self::assertSame(7, $result['age_seconds']);
        self::assertSame([], $result['rooms']);
        self::assertStringNotContainsString('must-not-leak', json_encode($result, JSON_THROW_ON_ERROR));
        self::assertArrayNotHasKey('max_players', $result);
        self::assertArrayNotHasKey('password', $result);
        self::assertArrayNotHasKey('map', $result);
        self::assertArrayNotHasKey('host', $result);
    }

    public function testApiContractDistinguishesMastersOnOneAddress(): void
    {
        $document = $this->fixture('api-multi-room');
        $result = $this->parse($document, '127.0.0.1:17779', clock: 1795816870);
        self::assertSame(3, $result['num_players']);
        self::assertSame(2, $result['room_count']);
        self::assertSame(1, $result['running_rooms']);
        self::assertIsArray($result['rooms']);
        self::assertCount(2, $result['rooms']);
        $other = $this->parse($document, clock: 1795816870);
        self::assertSame(0, $other['num_players']);
        self::assertNotSame($result['server_id'], $other['server_id']);
        self::assertSame([], $this->parse($document, '127.0.0.1:17778', clock: 1795816870));
    }

    public function testDuplicateIdsAndAmbiguousAddressesAreRejected(): void
    {
        $document = $this->fixture('controlled');
        $row = $this->row();
        $document['servers'] = [$row, $row];
        self::assertSame([], $this->parse($document));
        $other = $row;
        $other['server_id'] = str_repeat('a', 32);
        $document['servers'] = [$row, $other];
        self::assertSame([], $this->parse($document));
        self::assertNotSame([], $this->parse($document, options: ['server_id' => str_repeat('1', 32)]));
        self::assertSame([], $this->parse($document, options: ['server_id' => str_repeat('b', 32)]));
    }

    public function testAdvertisedDnsAndEquivalentIpv6DoNotRequireDirectoryDnsResolution(): void
    {
        $document = $this->fixture('controlled');
        $row = $this->row();
        $row['endpoint'] = 'games.example.test:17777';
        $document['servers'] = [$row];
        self::assertSame([], $this->parse($document));
        self::assertNotSame([], $this->parse($document, options: ['directory_address' => 'games.example.test']));
        $row['endpoint'] = '[0:0:0:0:0:0:0:1]:17777';
        $document['servers'] = [$row];
        self::assertNotSame([], $this->parse($document, '[::1]:17777'));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidRows(): iterable
    {
        yield 'stale' => [['stale' => true]];
        yield 'old heartbeat' => [['last_seen' => 1795816755]];
        yield 'future heartbeat' => [['last_seen' => 1795816900]];
        yield 'zero port' => [['master_port' => 0, 'endpoint' => '127.0.0.1:0']];
        yield 'overflow port' => [['master_port' => 65536, 'endpoint' => '127.0.0.1:65536']];
        yield 'credentials in endpoint' => [['endpoint' => 'user@127.0.0.1:17777']];
        yield 'path in endpoint' => [['endpoint' => '127.0.0.1:17777/anything']];
        yield 'wrong port type' => [['master_port' => '17777']];
        yield 'wrong count type' => [['players' => '0']];
        yield 'impossible player count' => [['players' => 257]];
        yield 'inconsistent player count' => [['players' => 1]];
        yield 'inconsistent room count' => [['room_count' => 1]];
        yield 'invalid capacity' => [['max_games' => 33]];
        yield 'invalid version' => [['version' => 'not-a-version']];
        yield 'control characters' => [['server_name' => "Bad\nName"]];
        yield 'bad room type' => [['room_count' => 1, 'rooms' => [null]]];
    }

    /** @param array<string, mixed> $overrides */
    #[DataProvider('invalidRows')]
    public function testMalformedOrExpiredRowsAreOffline(array $overrides): void
    {
        $document = $this->fixture('controlled');
        $document['servers'] = [array_replace($this->row(), $overrides)];
        self::assertSame([], $this->parse($document));
    }

    public function testMalformedDocumentsAndExcessiveEntriesAreOffline(): void
    {
        $document = $this->fixture('controlled');

        foreach ([['ok' => false], ['schema' => 2], ['generated_at' => 0], ['servers' => null]] as $overrides) {
            self::assertSame([], $this->parse(array_replace($document, $overrides)));
        }

        $document['servers'] = array_fill(0, 501, $this->row());
        self::assertSame([], $this->parse($document));
    }

    public function testFixedReadOnlyHttpRequestAndNormalization(): void
    {
        $now = time();
        $row = array_replace($this->row(), ['last_seen' => $now]);
        $document = array_replace($this->fixture('controlled'), ['generated_at' => $now, 'servers' => [$row]]);
        $client = new HttpClient([new Response(200, json_encode($document, JSON_THROW_ON_ERROR))]);
        $gameq = new GameQ();
        $gameq->setHttpClient($client);
        $gameq->addServer(['type' => 'dieneuekrone', 'host' => '127.0.0.1:17777']);
        $results = $gameq->process();
        self::assertTrue($results['127.0.0.1:17777']['gq_online']);
        self::assertSame('GameQ-Krone-Local-Test', $results['127.0.0.1:17777']['gq_hostname']);
        self::assertSame(0, $results['127.0.0.1:17777']['gq_numplayers']);
        self::assertCount(1, $client->requests);
        self::assertSame('GET', $client->requests[0]->method);
        self::assertSame('https://haas-lab.de/kaiser-serverlist/api/servers.php', $client->requests[0]->url);
        self::assertSame('', $client->requests[0]->body);
        self::assertSame(16 * 1024 * 1024, $client->requests[0]->maxResponseBytes);
        self::assertNull((new Server(['type' => 'dieneuekrone', 'host' => '127.0.0.1:17777']))->getJoinLink());
    }

    public function testHttpErrorsInvalidJsonAndTransportFailuresStayOffline(): void
    {
        $responses = [new Response(200, '{truncated'), new HttpException('redacted transport failure')];

        foreach ([301, 400, 401, 403, 404, 429, 500, 503] as $status) {
            $responses[] = new Response($status, '{"ok":false}');
        }

        foreach ($responses as $response) {
            $gameq = new GameQ();
            $client = new HttpClient([$response]);
            $gameq->setHttpClient($client);
            $gameq->addServer(['type' => 'dieneuekrone', 'host' => '127.0.0.1:17777']);
            self::assertFalse($gameq->process()['127.0.0.1:17777']['gq_online']);
            self::assertCount(1, $client->requests);
        }
    }
}
