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

use GameQ\GameQ;
use GameQ\Protocols\Scum as ScumProtocol;
use GameQ\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

/**
 * SCUM master-server protocol tests.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Scum extends Base
{
    public function testCurrentMasterResponseIsProcessedWithoutNetworkQuery(): void
    {
        $gameQ = new GameQ();
        $gameQ->addServer([
            Server::SERVER_ID => 'scum-test',
            Server::SERVER_TYPE => 'scum',
            Server::SERVER_HOST => '203.0.113.5:7777',
            Server::SERVER_OPTIONS => [
                'master_response' => $this->createMasterResponse($this->createServerRecord()),
            ],
        ]);

        $result = $gameQ->process()['scum-test'];

        self::assertTrue($result['gq_online']);
        self::assertSame('203.0.113.5', $result['gq_address']);
        self::assertSame(7777, $result['gq_port_client']);
        self::assertSame(7777, $result['gq_port_query']);
        self::assertSame('SCUM Test Server', $result['gq_hostname']);
        self::assertSame(64, $result['gq_maxplayers']);
        self::assertSame(7, $result['gq_numplayers']);
        self::assertSame('13:30', $result['time']);
        self::assertSame('1.3.4.0.160241', $result['version']);
        self::assertSame(30.5, $result['tps']);
        self::assertSame(2, $result['playstyle']);
        self::assertSame(1, $result['server_class']);
        self::assertSame(64, $result['flags']);
        self::assertSame('short', $result['scum_record_format']);
        self::assertSame('steam://connect/203.0.113.5:7777/', $result['gq_joinlink']);
    }

    public function testMissingMasterServerIsOffline(): void
    {
        $result = $this->queryTest(
            '203.0.113.5:7777',
            'scum',
            [],
            false,
            ['master_response' => "\x00\x00\x00\x00"],
        );

        self::assertFalse($result['gq_online']);
    }

    public function testMixedRecordsKeepTheirBoundaries(): void
    {
        $records = [
            $this->createServerRecord('Older server', false, 7001, "\xEF\xBE"),
            $this->createServerRecord('New server', true, 7002),
            $this->createServerRecord('Überlebende 日本', false, 7003),
            $this->createServerRecord('Last server', true, 7004),
        ];
        $response = $this->createMasterResponse(...$records);

        foreach (['Older server', 'New server', 'Überlebende 日本', 'Last server'] as $index => $name) {
            $result = $this->queryMaster($response, 7001 + $index);

            self::assertTrue($result['gq_online']);
            self::assertSame($name, $result['gq_hostname']);
            self::assertSame(7, $result['gq_numplayers']);
            self::assertSame(64, $result['gq_maxplayers']);
            self::assertSame('13:30', $result['time']);
            self::assertSame(30.5, $result['tps']);
            self::assertSame('1.3.4.0.160241', $result['version']);
        }
    }

    #[DataProvider('capturedRecords')]
    public function testCapturedMasterRecord(
        string $hex,
        int $port,
        int $nameLength,
        string $format,
        int $players,
        int $maxplayers,
        string $time,
        float $tps,
        string $version,
    ): void {
        $record = hex2bin($hex);
        self::assertIsString($record);
        $result = $this->queryMaster($this->createMasterResponse($record), $port);

        self::assertTrue($result['gq_online']);
        self::assertSame(str_repeat('S', $nameLength), $result['gq_hostname']);
        self::assertSame($format, $result['scum_record_format']);
        self::assertSame($players, $result['gq_numplayers']);
        self::assertSame($maxplayers, $result['gq_maxplayers']);
        self::assertSame($time, $result['time']);
        self::assertSame($tps, $result['tps']);
        self::assertSame($version, $result['version']);
    }

    /**
     * Captured from proxy-2 on 2026-10-07; only IPs and name bytes are anonymized.
     *
     * @return list<array{string, int, int, string, int, int, string, float, string}>
     */
    public static function capturedRecords(): array
    {
        return [
            [
                '057100cb6e1b00adde435301728060000015b28b41410c6c410000101f2700040359'
                    . str_repeat('53', 89) . '00efbe',
                7022, 89, 'long', 114, 128, '17:27', 14.752991676330566, '1.3.4.0.160241',
            ],
            [
                '057100cb667d01adde435301040a400000cd831441ec75f0410000101f2700040323'
                    . str_repeat('53', 35) . '00efbe',
                32102, 35, 'long', 4, 10, '09:16', 30.057579040527344, '1.3.4.0.160241',
            ],
            [
                '057100cbe61b02adde00400000c82d6140750fa04078e441691e0001032d'
                    . str_repeat('53', 45) . '00efbe',
                7142, 45, 'short', 0, 64, '03:31', 5.00188684463501, '1.3.1.0.124564',
            ],
            [
                '057100cb5a1b01adde000a0000eb2d0441552ca040000060d91a0202020453535353efbe',
                7002, 4, 'short', 0, 10, '08:15', 5.005411624908447, '1.2.2.2.109974',
            ],
        ];
    }

    public function testAmbiguousLongRecordUsesExtensionDiscriminator(): void
    {
        // The packed build makes byte +29 equal 39. A 35-byte name therefore
        // places both candidate terminators at the same position.
        $record = $this->createServerRecord(str_repeat('L', 35), true);
        $result = $this->queryMaster($this->createMasterResponse($record));

        self::assertTrue($result['gq_online']);
        self::assertSame(str_repeat('L', 35), $result['gq_hostname']);
        self::assertSame('long', $result['scum_record_format']);
        self::assertSame(7, $result['gq_numplayers']);
        self::assertSame(64, $result['gq_maxplayers']);
        self::assertSame('1.3.4.0.160241', $result['version']);
    }

    public function testAmbiguousShortRecordStaysShortWithoutExtension(): void
    {
        // The fourth name byte doubles as a plausible long-layout length byte.
        $name = 'ABC' . chr(30) . str_repeat('S', 30);
        $result = $this->queryMaster($this->createMasterResponse($this->createServerRecord($name)));

        self::assertTrue($result['gq_online']);
        self::assertSame($name, $result['gq_hostname']);
        self::assertSame('short', $result['scum_record_format']);
    }

    public function testUnambiguousLongRecordDoesNotRequireKnownExtensionBytes(): void
    {
        $record = substr_replace($this->createServerRecord('New extension', true), "\x44\x00\x02", 9, 3);
        $result = $this->queryMaster($this->createMasterResponse($record));

        self::assertTrue($result['gq_online']);
        self::assertSame('New extension', $result['gq_hostname']);
        self::assertSame('long', $result['scum_record_format']);
    }

    public function testAddressInsideHostnameCannotBeMistakenForARecord(): void
    {
        $embeddedRecord = $this->createServerRecord();
        $outerRecord = $this->createServerRecord('Prefix' . $embeddedRecord, true, 7001);
        $result = $this->queryMaster($this->createMasterResponse($outerRecord));

        self::assertFalse($result['gq_online']);
    }

    public function testZeroTpsDoesNotCauseAnOtherwiseValidServerToBeDiscarded(): void
    {
        $record = substr_replace($this->createServerRecord(), pack('g', 0.0), 17, 4);
        $result = $this->queryMaster($this->createMasterResponse($record));

        self::assertTrue($result['gq_online']);
        self::assertSame(0.0, $result['tps']);
        self::assertSame('short', $result['scum_record_format']);
    }

    public function testSignedPlaystyleAndPackedVersionAreDecoded(): void
    {
        $record = substr_replace($this->createServerRecord(), pack('c', -1), 12, 1);
        $record = substr_replace($record, pack('VCC', (2 << 24) | (109974 << 4) | 15, 2, 2), 23, 6);
        $result = $this->queryMaster($this->createMasterResponse($record));

        self::assertSame(-1, $result['playstyle']);
        self::assertSame('1.2.2.2.109974', $result['version']);
    }

    public function testMalformedRecordsCannotResynchronizeInsideTheBlob(): void
    {
        $invalid = substr_replace($this->createServerRecord('Invalid', false, 7001), "\x00\x00", 7, 2);
        $response = $this->createMasterResponse($invalid, $this->createServerRecord());

        self::assertFalse($this->queryMaster($response)['gq_online']);
    }

    public function testMalformedTrailingRecordInvalidatesTheWholeList(): void
    {
        $response = $this->createMasterResponse($this->createServerRecord(), 'truncated');

        self::assertFalse($this->queryMaster($response)['gq_online']);
    }

    public function testAllTruncatedResponsesAreRejected(): void
    {
        $response = $this->createMasterResponse($this->createServerRecord('Truncation test', true));

        for ($length = 0; $length < strlen($response); ++$length) {
            $truncated = substr($response, 0, $length);

            self::assertFalse($this->queryMaster($truncated)['gq_online'], 'Unmodified length prefix at length ' . $length);

            if ($length > 4) {
                $truncated = pack('V', $length - 4) . substr($truncated, 4);
                self::assertFalse($this->queryMaster($truncated)['gq_online'], 'Matching length prefix at length ' . $length);
            }
        }
    }

    public function testIncorrectLengthPrefixesAndMissingTerminatorsAreRejected(): void
    {
        $record = $this->createServerRecord();
        $invalidResponses = [
            pack('V', 1) . $record,
            pack('V', strlen($record) + 1) . $record,
            pack('V', 0xFFFFFFFF) . $record,
            $this->createMasterResponse(substr($record, 0, -3)),
            $this->createMasterResponse(substr_replace($record, "\xFF", 29, 1)),
            $this->createMasterResponse(substr_replace($record, "\x00\x00", 7, 2)),
        ];

        foreach ($invalidResponses as $response) {
            self::assertFalse($this->queryMaster($response)['gq_online']);
        }
    }

    #[DataProvider('nonFiniteValues')]
    public function testNonFiniteTimeAndTpsAreRejected(int $offset, float $value): void
    {
        $record = substr_replace($this->createServerRecord(), pack('g', $value), $offset, 4);

        self::assertFalse($this->queryMaster($this->createMasterResponse($record))['gq_online']);
    }

    /** @return list<array{int, float}> */
    public static function nonFiniteValues(): array
    {
        return [[13, NAN], [13, INF], [17, NAN], [17, INF]];
    }

    public function testReusedProtocolClearsPreviousServerData(): void
    {
        $protocol = new ScumProtocol(['master_response' => $this->createMasterResponse($this->createServerRecord())]);
        $server = new Server([Server::SERVER_TYPE => 'scum', Server::SERVER_HOST => '203.0.113.5:7777']);
        $missing = new Server([Server::SERVER_TYPE => 'scum', Server::SERVER_HOST => '203.0.113.5:7000']);
        $protocol->beforeSend($server);
        self::assertSame('SCUM Test Server', $protocol->processResponse()['hostname']);
        $protocol->beforeSend($missing);
        self::assertSame([], $protocol->processResponse());
    }

    public function testMasterReaderStopsAtTheDeclaredLengthWithoutWaitingForEof(): void
    {
        // Exactly one read-sized frame leaves EOF unset on the stream. A complete
        // length-prefixed response must still finish without another socket read.
        $payload = str_repeat('S', 32764);
        $response = $this->createMasterResponse($payload);
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);

        try {
            fwrite($stream, $response);
            rewind($stream);
            $method = new ReflectionMethod(ScumProtocol::class, 'readMasterResponse');
            self::assertSame($response, $method->invoke(new ScumProtocol(), $stream));
            self::assertFalse(feof($stream));
        } finally {
            fclose($stream);
        }
    }

    public function testMasterReaderRejectsIncompleteOversizedAndTrailingData(): void
    {
        $record = $this->createServerRecord();
        $responses = [
            '',
            "\x00\x00\x00",
            pack('V', strlen($record) + 1) . $record,
            pack('V', 0xFFFFFFFF),
            $this->createMasterResponse($record) . 'trailing',
        ];
        $method = new ReflectionMethod(ScumProtocol::class, 'readMasterResponse');

        foreach ($responses as $response) {
            $stream = fopen('php://memory', 'w+b');
            self::assertIsResource($stream);

            try {
                fwrite($stream, $response);
                rewind($stream);
                self::assertNull($method->invoke(new ScumProtocol(), $stream));
            } finally {
                fclose($stream);
            }
        }
    }

    /** @return array<string, mixed> */
    private function queryMaster(string $response, int $port = 7777): array
    {
        $gameQ = new GameQ();
        $gameQ->addServer([
            Server::SERVER_ID => 'scum-test',
            Server::SERVER_TYPE => 'scum',
            Server::SERVER_HOST => '203.0.113.5:' . $port,
            Server::SERVER_OPTIONS => ['master_response' => $response],
        ]);

        return $gameQ->process()['scum-test'];
    }

    private function createMasterResponse(string ...$records): string
    {
        $payload = implode('', $records);

        return pack('V', strlen($payload)) . $payload;
    }

    private function createServerRecord(
        string $name = 'SCUM Test Server',
        bool $long = false,
        int $port = 7777,
        string $terminator = "\x00\xEF\xBE",
    ): string {
        return pack('C4v', 5, 113, 0, 203, $port)
            . "\x01\xAD\xDE"
            . ($long ? "\x43\x53\x01" : '')
            . pack('CC', 7, 64)
            . "\x40"
            . ($long ? "\x00" : '')
            . pack('c', 2)
            . pack('g', 13.5)
            . pack('g', 30.5)
            . "\x00\x00"
            . pack('VCC', 160241 << 4, 4, 3)
            . pack('C', strlen($name))
            . $name
            . $terminator;
    }
}
