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

namespace GameQ\Tests;

use GameQ\Tests\Fixtures\BatchQuery;
use GameQ\Tests\Fixtures\FollowUpQuery;
use ReflectionClass;
use RuntimeException;

/**
 * GameQ tests class
 *
 * @package GameQ\Tests
 */
class GameQ extends TestBase
{
    /**
     * Holds stub on setup
     *
     * @var \GameQ\GameQ
     */
    protected \GameQ\GameQ $stub;

    /**
     * Setup to create our stub
     */
    #[\PHPUnit\Framework\Attributes\Before]
    public function customSetUp(): void
    {
        $this->stub = new \GameQ\GameQ();
    }

    /**
     * Test factory
     */
    public function testFactory(): void
    {
        self::assertNotSame(\GameQ\GameQ::factory(), \GameQ\GameQ::factory());
    }

    /**
     * Test getters and setters
     */
    public function testGetSetOptions(): void
    {
        // Test null return for missing option
        self::assertNull($this->stub->__get('invalidoption'));

        $this->stub->__set('option1', 'value1');

        // Verify the pull is correct
        self::assertEquals('value1', $this->stub->__get('option1'));

        // Use set option chainable
        $this->stub->setOption('option1', 'value2');

        // Verify the pull is correct
        self::assertEquals('value2', $this->stub->__get('option1'));
    }

    /**
     * Test adding/removing servers
     */
    public function testAddServer(): void
    {
        // Define some servers
        $servers = [
            [
                \GameQ\Server::SERVER_HOST => '127.0.0.1:27015',
                \GameQ\Server::SERVER_TYPE => 'css',
            ],
            [
                \GameQ\Server::SERVER_HOST => '127.0.0.1:27016',
                \GameQ\Server::SERVER_TYPE => 'css',
            ],
            [
                \GameQ\Server::SERVER_HOST => '127.0.0.1:27017',
                \GameQ\Server::SERVER_TYPE => 'css',
            ],
        ];

        // Test single add server
        $this->stub->addServer($servers[0]);

        self::assertCount(1, $this->stub->getServers());

        // Clear the servers
        $this->stub->clearServers();

        self::assertCount(0, $this->stub->getServers());

        // Add multiple servers
        $this->stub->addServers($servers);

        self::assertCount(3, $this->stub->getServers());

        $this->stub->clearServers();
    }

    /**
     * Test adding servers from files
     *
     */
    #[\PHPUnit\Framework\Attributes\Depends('testAddServer')]
    public function testAddServersFromFiles(): void
    {
        // Test single file
        $this->stub->addServersFromFiles(__DIR__ . '/Protocols/Providers/server_list1.json');

        self::assertCount(2, $this->stub->getServers());

        $this->stub->clearServers();

        // Test adding from json array of files
        $this->stub->addServersFromFiles([
            __DIR__ . '/Protocols/Providers/server_list1.json',
        ]);

        self::assertCount(2, $this->stub->getServers());

        $this->stub->clearServers();

        // Test adding bad file
        $this->stub->addServersFromFiles([
            __DIR__ . '/Protocols/Providers/server_list_bad.json',
        ]);

        // No servers should exist
        self::assertCount(0, $this->stub->getServers());

        $this->stub->clearServers();

        // Test inaccessible file
        $this->stub->addServersFromFiles(__DIR__ . '/Protocols/Providers/server_listDoesnotexist.json');

        // No servers should exist
        self::assertCount(0, $this->stub->getServers());

        $this->stub->clearServers();
    }

    /**
     * Test adding/removing filters
     */
    public function testFiltersAddRemove(): void
    {
        // Add filter
        $this->stub->addFilter('test_filter');

        self::assertArrayHasKey(
            'test_filter_d751713988987e9331980363e24189ce',
            $this->stub->listFilters(),
        );

        // Remove filter
        $this->stub->removeFilter('test_filter_d751713988987e9331980363e24189ce');

        self::assertArrayNotHasKey(
            'test_filter_d751713988987e9331980363e24189ce',
            $this->stub->listFilters(),
        );

        // Test for lower case always
        $this->stub->addFilter('tEst_fiLTEr');

        self::assertArrayHasKey(
            'test_filter_d751713988987e9331980363e24189ce',
            $this->stub->listFilters(),
        );

        // Remove filter always lower case
        $this->stub->removeFilter('tEst_fiLTEr_d751713988987e9331980363e24189ce');

        self::assertArrayNotHasKey(
            'test_filter_d751713988987e9331980363e24189ce',
            $this->stub->listFilters(),
        );
    }

    /**
     * Test filter application
     */
    public function testFilterApply(): void
    {
        // Define some fake results
        $fakeResults = [
            'key1' => 'val1',
            'key2' => 'val2',
        ];

        // Create a mock server
        $server = new \GameQ\Server([
            \GameQ\Server::SERVER_HOST => '127.0.0.1:27015',
            \GameQ\Server::SERVER_TYPE => 'css',
        ]);

        $this->stub->setOption('debug', false);
        $this->stub->removeFilter('normalize_d751713988987e9331980363e24189ce');
        $this->stub->addFilter('test');

        // Reflect on GameQ class so we can parse
        $gameq = new ReflectionClass($this->stub);

        // Get the parse method so we can call it
        $method = $gameq->getMethod('doApplyFilters');

        $testResult = $method->invoke($this->stub, $fakeResults, $server);

        self::assertEquals($fakeResults, $testResult);
    }

    /**
     * Test for bad filter and no exception is thrown
     */
    public function testBadFilterException(): void
    {
        // Define some fake results
        $fakeResults = [
            'key1' => 'val1',
            'key2' => 'val2',
        ];

        // Create a mock server
        $server = new \GameQ\Server([
            \GameQ\Server::SERVER_HOST => '127.0.0.1:27015',
            \GameQ\Server::SERVER_TYPE => 'css',
        ]);

        $this->stub->setOption('debug', false);
        $this->stub->removeFilter('normalize_d751713988987e9331980363e24189ce');
        $this->stub->addFilter('some_bad_filter');

        // Reflect on GameQ class so we can parse
        $gameq = new ReflectionClass($this->stub);

        // Get the parse method so we can call it
        $method = $gameq->getMethod('doApplyFilters');

        // No changes should be made
        $testResult = $method->invoke($this->stub, $fakeResults, $server);

        self::assertEquals($fakeResults, $testResult);
    }

    public function testResponseDrivenFollowUpQueriesAreCollected(): void
    {
        $challengeToken = str_repeat("\xCC", 32);
        FollowUpQuery::$roundResponses = [
            ['ONEREPLY' . "\x00" . $challengeToken . str_repeat("\x00", 7)],
            [
                self::oneQueryPacket(0, 1, self::oneQueryTlv(1, self::oneQueryServerInfo())),
                self::oneQueryPacket(1, 2, self::oneQueryTlv(
                    2,
                    self::oneQueryPlayerPage(2, 0, 'Alice', '11111111222233334444555555555555'),
                )),
            ],
            [self::oneQueryPacket(0, 3, self::oneQueryTlv(
                2,
                self::oneQueryPlayerPage(2, 1, 'Bob', 'aaaaaaaabbbbccccddddeeeeeeeeeeee'),
            ))],
        ];
        FollowUpQuery::clearWrites();

        $reflection = new ReflectionClass($this->stub);
        $reflection->getProperty('queryLibrary')->setValue($this->stub, FollowUpQuery::class);
        $this->stub->addServer([
            'id' => 'hytale-pagination',
            'type' => 'hytaleone',
            'host' => '127.0.0.1:5520',
        ]);

        $result = $this->stub->process()['hytale-pagination'];

        self::assertCount(4, FollowUpQuery::$writes);
        self::assertSame(2, $result['players_total']);
        self::assertSame(2, $result['players_returned']);
        self::assertFalse($result['players_truncated']);

        $players = $result['players'] ?? null;
        self::assertIsArray($players);
        self::assertIsArray($players[0] ?? null);
        self::assertIsArray($players[1] ?? null);
        self::assertSame('Alice', $players[0]['name']);
        self::assertSame('Bob', $players[1]['name']);
    }

    public function testSourceQueriesUseServerDrivenChallenges(): void
    {
        $infoResponse = "\xFF\xFF\xFF\xFF\x49\x11"
            . "Counter-Strike 2 Server\x00de_dust2\x00csgo\x00Counter-Strike 2\x00"
            . pack('vCCC', 730, 1, 64, 0)
            . "dl"
            . pack('CC', 0, 1)
            . "1.41.1.2\x00";
        FollowUpQuery::$roundResponses = [
            ["\xFF\xFF\xFF\xFF\x41info"],
            [$infoResponse],
            ["\xFF\xFF\xFF\xFF\x41play"],
            ["\xFF\xFF\xFF\xFF\x44\x00"],
            ["\xFF\xFF\xFF\xFF\x41rule"],
            ["\xFF\xFF\xFF\xFF\x45\x00\x00"],
        ];
        FollowUpQuery::clearWrites();

        $reflection = new ReflectionClass($this->stub);
        $reflection->getProperty('queryLibrary')->setValue($this->stub, FollowUpQuery::class);
        $this->stub->addServer([
            'id' => 'source-challenge',
            'type' => 'cs2',
            'host' => '127.0.0.1:27015',
        ]);

        $result = $this->stub->process()['source-challenge'];

        self::assertSame([
            "\xFF\xFF\xFF\xFFTSource Engine Query\x00",
            "\xFF\xFF\xFF\xFFTSource Engine Query\x00info",
            "\xFF\xFF\xFF\xFF\x55info",
            "\xFF\xFF\xFF\xFF\x55play",
            "\xFF\xFF\xFF\xFF\x56play",
            "\xFF\xFF\xFF\xFF\x56rule",
        ], FollowUpQuery::$writes);
        self::assertTrue($result['gq_online']);
        self::assertSame('Counter-Strike 2 Server', $result['gq_hostname']);
        self::assertSame(730, $result['steamappid']);
    }

    public function testServersAreProcessedInConfiguredBatches(): void
    {
        BatchQuery::resetMetrics();

        $reflection = new ReflectionClass($this->stub);
        $reflection->getProperty('queryLibrary')->setValue($this->stub, BatchQuery::class);
        $this->stub->setOption('max_servers_per_batch', 2);

        for ($port = 27015; $port < 27020; ++$port) {
            $this->stub->addServer([
                'type' => 'source',
                'host' => '127.0.0.1:' . $port,
            ]);
        }

        $results = $this->stub->process();

        self::assertCount(5, $results);
        self::assertNotSame([], BatchQuery::$socketCounts);
        self::assertLessThanOrEqual(2, max(BatchQuery::$socketCounts));
        self::assertCount(5, $this->stub->getServers());
    }

    private static function oneQueryServerInfo(): string
    {
        return self::oneQueryString('Server')
            . self::oneQueryString('MOTD')
            . pack('V', 2)
            . pack('V', 100)
            . self::oneQueryString('2026.07.30')
            . pack('V', 7)
            . self::oneQueryString('DEADBEEF');
    }

    private static function oneQueryPlayerPage(
        int $total,
        int $offset,
        string $name,
        string $uuidHex,
    ): string {
        $uuid = hex2bin($uuidHex);

        if ($uuid === false) {
            throw new RuntimeException('Unable to encode the test UUID.');
        }

        return pack('V', $total)
            . pack('V', 1)
            . pack('V', $offset)
            . self::oneQueryString($name)
            . $uuid;
    }

    private static function oneQueryString(string $value): string
    {
        return pack('v', strlen($value)) . $value;
    }

    private static function oneQueryTlv(int $type, string $value): string
    {
        return pack('v', $type) . pack('v', strlen($value)) . $value;
    }

    private static function oneQueryPacket(int $flags, int $requestId, string $payload): string
    {
        return 'ONEREPLY'
            . "\x01"
            . pack('v', $flags)
            . pack('V', $requestId)
            . pack('v', strlen($payload))
            . $payload;
    }
}
