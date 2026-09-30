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

namespace GameQ\Tests\Http;

use GameQ\Http\CurlClient;
use GameQ\Http\GuzzleClient;
use GameQ\Http\HttpException;
use GameQ\Http\Request;
use GameQ\Tests\Fixtures\HttpServer;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use JsonException;
use PHPUnit\Framework\TestCase;

/**
 * Real local transfer tests for proxy routing, redirects, and decoded size limits.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
final class LocalTransfer extends TestCase
{
    private static HttpServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = new HttpServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    /**
     * @throws JsonException
     */
    public function testCurlAndGuzzleTransferBodiesAndHeadersAndRefuseRedirects(): void
    {
        $factory = new HttpFactory();
        $clients = [new CurlClient(), new GuzzleClient(new Client(), $factory, $factory)];

        foreach ($clients as $client) {
            $response = $client->send(new Request(
                'POST',
                self::$server->url . '/echo',
                ['Authorization' => 'Bearer local-test', 'Content-Type' => 'application/json'],
                '{"test":true}',
            ));

            $data = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($data);
            self::assertSame(200, $response->statusCode);
            self::assertSame('POST', $data['method']);
            self::assertSame('Bearer local-test', $data['authorization']);
            self::assertSame('{"test":true}', $data['body']);
            $redirect = $client->send(new Request('GET', self::$server->url . '/redirect'));
            self::assertSame(302, $redirect->statusCode);
            self::assertSame(['/echo'], $redirect->header('Location'));
        }
    }

    /**
     * @throws JsonException
     */
    public function testGuzzleSendsAbsoluteUrlThroughConfiguredProxy(): void
    {
        $factory = new HttpFactory();
        $client = new GuzzleClient(new Client(['proxy' => self::$server->url]), $factory, $factory);
        $response = $client->send(new Request('GET', 'http://gameq.invalid/echo'));
        $data = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame('http://gameq.invalid/echo', $data['uri']);
    }

    public function testBothTransportsEnforceDecodedBodyLimitDuringTransfer(): void
    {
        $factory = new HttpFactory();

        foreach ([new CurlClient(), new GuzzleClient(new Client(), $factory, $factory)] as $client) {
            foreach (['/large', '/gzip'] as $path) {
                try {
                    $client->send(new Request('GET', self::$server->url . $path, maxResponseBytes: 8));
                    self::fail('Oversized decoded response was accepted.');
                } catch (HttpException $exception) {
                    self::assertSame('HTTP transfer failed.', $exception->getMessage());
                }
            }
        }
    }
}
