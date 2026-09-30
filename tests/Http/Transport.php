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

use GameQ\Exception\ProtocolException;
use GameQ\Exception\QueryException;
use GameQ\Exception\ServerException;
use GameQ\GameQ;
use GameQ\Http\GuzzleClient;
use GameQ\Http\HttpException;
use GameQ\Http\Psr18Client;
use GameQ\Http\Request;
use GameQ\Http\Response;
use GameQ\Protocols\Eos;
use GameQ\Server;
use GameQ\Tests\Fixtures\HttpClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response as PsrResponse;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * Regression coverage for injectable HTTP transports and protocol integration.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
final class Transport extends TestCase
{
    /**
     * @throws ServerException
     */
    public function testInjectionUpdatesExistingAndFutureServers(): void
    {
        $client = new HttpClient([]);
        $gameQ = new GameQ();
        $gameQ->addServer(['type' => 'css', 'host' => '127.0.0.1:27015']);
        self::assertSame($gameQ, $gameQ->setHttpClient($client));
        $gameQ->addServer(['type' => 'css', 'host' => '127.0.0.1:27016']);

        foreach ($gameQ->getServers() as $server) {
            self::assertSame($client, $server->protocolInstance()->getHttpClient());
        }
    }

    /**
     * @throws QueryException
     * @throws ProtocolException
     * @throws ServerException
     */
    public function testLegacyHttpQueryUsesInjectedClientIncludingIpv6(): void
    {
        $client = new HttpClient([new Response(200, '{"name":"Test","players":[],"maxPlayers":12}')]);
        $gameQ = (new GameQ())->setHttpClient($client)->setOption('timeout', 7);
        $gameQ->addServer(['id' => 'http', 'type' => 'buildandshoot', 'host' => '[::1]:32887']);
        $result = $gameQ->process()['http'];

        self::assertTrue($result['gq_online']);
        self::assertCount(1, $client->requests);
        self::assertSame('GET', $client->requests[0]->method);
        self::assertSame('http://[::1]:32886/json', $client->requests[0]->url);
        self::assertSame(7, $client->requests[0]->timeout);
    }

    /**
     * @throws QueryException
     * @throws ProtocolException
     * @throws ServerException
     */
    public function testLegacyHttpErrorsAreOfflineAndDoNotParseErrorBodies(): void
    {
        foreach ([new Response(302, '{}'), new Response(500, '{}'), new HttpException('timeout')] as $response) {
            $gameQ = (new GameQ())->setHttpClient(new HttpClient([$response]));
            $gameQ->addServer(['id' => 'http', 'type' => 'eco', 'host' => '127.0.0.1:3001']);
            self::assertFalse($gameQ->process()['http']['gq_online']);
        }
    }

    /**
     * @throws ProtocolException
     * @throws ServerException
     */
    public function testFiveMHttpSubqueriesInheritTheInjectedClient(): void
    {
        $client = new HttpClient([
            new Response(200, '[{"name":"Alice","id":1}]'),
            new Response(200, '{"vars":{"sv_maxClients":"32"},"version":1}'),
        ]);
        $server = new Server(['type' => 'cfx', 'host' => '127.0.0.1:30120']);
        $protocol = $server->protocolInstance()->setHttpClient($client);
        $protocol->beforeSend($server);
        $result = $protocol->processResponse();
        self::assertCount(2, $client->requests);
        self::assertSame('http://127.0.0.1:30120/players.json', $client->requests[0]->url);
        self::assertSame('http://127.0.0.1:30120/info.json', $client->requests[1]->url);
        self::assertSame('32', $result['sv_maxclients']);
    }

    public function testEosUsesInjectedPostClientAndPreservesHttpsAndLimits(): void
    {
        $client = new HttpClient([new Response(200, '{"access_token":"test-token"}')]);
        $protocol = new class (['http_timeout' => 999]) extends Eos {
            /** @return array<string, mixed>|null */
            public function requestForTest(string $url): ?array
            {
                return $this->httpRequest($url, ['Authorization: Basic test', 'Content-Type: application/json'], '{}');
            }
        };
        $protocol->setHttpClient($client);
        self::assertSame(['access_token' => 'test-token'], $protocol->requestForTest('https://api.epicgames.dev/test'));
        self::assertNull($protocol->requestForTest('http://api.epicgames.dev/test'));
        self::assertCount(1, $client->requests);
        $request = $client->requests[0];
        self::assertSame('POST', $request->method);
        self::assertSame('{}', $request->body);
        self::assertSame('Basic test', $request->headers['Authorization']);
        self::assertSame(30, $request->timeout);
        self::assertSame(8 * 1024 * 1024, $request->maxResponseBytes);
    }

    /**
     * @throws QueryException
     * @throws ServerException
     * @throws ProtocolException
     */
    public function testWardogsUsesInjectedBearerClient(): void
    {
        $client = new HttpClient([
            new Response(200, '{"serverName":"Test","players":{"current":0,"max":64}}'),
            new Response(200, '{"routes":["GET /v1/status"]}'),
        ]);
        $gameQ = (new GameQ())->setHttpClient($client);
        $gameQ->addServer([
            'id' => 'wardogs', 'type' => 'wardogs', 'host' => '127.0.0.1:7777',
            'options' => ['query_port' => 8080, 'rcon_password' => 'test-secret', 'rcon_scheme' => 'https'],
        ]);
        self::assertTrue($gameQ->process()['wardogs']['gq_online']);
        self::assertCount(2, $client->requests);
        self::assertSame('https://127.0.0.1:8080/v1/status', $client->requests[0]->url);
        self::assertSame('Bearer test-secret', $client->requests[0]->headers['Authorization']);
    }

    /**
     * @throws QueryException
     * @throws ServerException
     * @throws ProtocolException
     */
    public function testPalworldUsesInjectedBasicClient(): void
    {
        $client = new HttpClient([
            new Response(200, '{"servername":"Test"}'), new Response(200, '{"players":[]}'),
            new Response(200, '{"maxplayernum":16}'), new Response(200, '{}'),
        ]);
        $gameQ = (new GameQ())->setHttpClient($client);
        $gameQ->addServer([
            'id' => 'palworld', 'type' => 'palworld', 'host' => '127.0.0.1:8211',
            'options' => ['admin_password' => 'test-secret'],
        ]);
        self::assertTrue($gameQ->process()['palworld']['gq_online']);
        self::assertCount(4, $client->requests);
        self::assertSame('Basic ' . base64_encode('admin:test-secret'), $client->requests[0]->headers['Authorization']);
    }

    /**
     * @throws ServerException
     * @throws ProtocolException
     * @throws QueryException
     */
    public function testWindroseLoginCookiesStayWithinEachQuery(): void
    {
        $status = '{"server":{"name":"Test","game":"Windrose","windrose_plus":"1.3.17","max_players":10,"player_count":0}}';
        $client = new HttpClient([
            new Response(302, '', ['Set-Cookie' => ['session=one; Path=/; HttpOnly', 'bad=no; Domain=other.test']]),
            new Response(200, $status),
            new Response(302, '', ['Set-Cookie' => ['session=two; Path=/; HttpOnly']]),
            new Response(200, $status),
        ]);
        $gameQ = (new GameQ())->setHttpClient($client);
        $gameQ->addServer([
            'id' => 'windrose', 'type' => 'windrose', 'host' => '127.0.0.1:7777',
            'options' => ['dashboard_password' => 'test-secret'],
        ]);
        self::assertTrue($gameQ->process()['windrose']['gq_online']);
        self::assertTrue($gameQ->process()['windrose']['gq_online']);
        self::assertSame('POST', $client->requests[0]->method);
        self::assertSame('password=test-secret', $client->requests[0]->body);
        self::assertArrayNotHasKey('Cookie', $client->requests[0]->headers);
        self::assertSame('session=one', $client->requests[1]->headers['Cookie']);
        self::assertArrayNotHasKey('Cookie', $client->requests[2]->headers);
        self::assertSame('session=two', $client->requests[3]->headers['Cookie']);
    }

    /**
     * @throws ServerException
     * @throws ProtocolException
     * @throws QueryException
     * @throws JsonException
     */
    public function testDirectoryCacheIsSharedWithinATransportAndIsolatedAcrossClients(): void
    {
        foreach (['First', 'Second'] as $name) {
            $client = new HttpClient([new Response(200, json_encode(['list' => [
                ['address' => '127.0.0.1', 'port' => 30000, 'name' => $name, 'clients' => 0, 'clients_max' => 10],
            ]], JSON_THROW_ON_ERROR))]);
            $gameQ = (new GameQ())->setHttpClient($client);
            $gameQ->addServer(['id' => 'directory', 'type' => 'minetest', 'host' => '127.0.0.1:30000']);
            self::assertSame($name, $gameQ->process()['directory']['gq_hostname']);
            self::assertSame($name, $gameQ->process()['directory']['gq_hostname']);
            self::assertCount(1, $client->requests);
            self::assertSame(16 * 1024 * 1024, $client->requests[0]->maxResponseBytes);
        }
    }

    /**
     * @throws QueryException
     * @throws ProtocolException
     * @throws ServerException
     */
    public function testBeammpAndFactorioUseInjectedClients(): void
    {
        $client = new HttpClient([
            new Response(200, '[{"ip":"127.0.0.1","port":30814,"sname":"Beam","maxplayers":10}]'),
            new Response(200, '{"name":"Factory","max_players":10}'),
        ]);
        $gameQ = (new GameQ())->setHttpClient($client);
        $gameQ->addServers([
            ['id' => 'beam', 'type' => 'beammp', 'host' => '127.0.0.1:30814'],
            ['id' => 'factorio', 'type' => 'factorio', 'host' => '127.0.0.1:34197'],
        ]);
        $result = $gameQ->process();
        self::assertSame('Beam', $result['beam']['gq_hostname']);
        self::assertSame('Factory', $result['factorio']['gq_hostname']);
        self::assertSame('POST', $client->requests[0]->method);
        self::assertSame('GET', $client->requests[1]->method);
    }

    public function testPsr18AdapterPreservesHttpErrorStatusAndRequestData(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new PsrResponse(401, ['X-Test' => 'yes'], '{}')]));
        $stack->push(Middleware::history($history));
        $factory = new HttpFactory();
        $client = new Psr18Client(new Client(['handler' => $stack]), $factory, $factory);
        $response = $client->send(new Request('POST', 'https://example.test/', ['Authorization' => 'test'], 'body'));
        self::assertSame(401, $response->statusCode);
        self::assertSame(['yes'], $response->header('x-test'));
        $transaction = self::firstHistoryTransaction($history);
        $sentRequest = $transaction['request'];
        self::assertSame('body', (string) $sentRequest->getBody());
        self::assertSame('test', $sentRequest->getHeaderLine('Authorization'));
    }

    public function testGuzzleAdapterPreservesProxyAndAppliesTransportPolicy(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new PsrResponse(302, ['Location' => 'https://other.test/'])]));
        $stack->push(Middleware::history($history));
        $factory = new HttpFactory();
        $client = new GuzzleClient(new Client(['handler' => $stack, 'proxy' => 'http://proxy.test:3128']), $factory, $factory);
        self::assertSame(302, $client->send(new Request('GET', 'https://example.test/', timeout: 9))->statusCode);
        $transaction = self::firstHistoryTransaction($history);
        $options = $transaction['options'];
        self::assertSame('http://proxy.test:3128', $options['proxy']);
        self::assertFalse($options['allow_redirects']);
        self::assertTrue($options['verify']);
        self::assertSame(9, $options['timeout']);
        self::assertSame(9, $options['connect_timeout']);
    }

    public function testPsr18RejectsOversizedBodiesWithAndWithoutKnownSize(): void
    {
        foreach ([true, false] as $knownSize) {
            $body = Utils::streamFor(str_repeat('x', 9));

            if (!$knownSize) {
                $body = FnStream::decorate($body, ['getSize' => static fn(): ?int => null]);
            }

            $factory = new HttpFactory();
            $client = new Psr18Client(new Client(['handler' => new MockHandler([new PsrResponse(200, [], $body)])]), $factory, $factory);

            try {
                $client->send(new Request('GET', 'https://example.test/', maxResponseBytes: 8));
                self::fail('Oversized response was accepted.');
            } catch (HttpException) {
                self::assertFalse($body->isReadable());
            }
        }
    }

    public function testUnsafeUrlsAndHeaderInjectionAreRejectedBeforeTransport(): void
    {
        $urls = [
            'file:///etc/passwd', 'ftp://example.test/', 'https://user:secret@example.test/', "http://example.test/\r\nX: bad",
        ];

        foreach ($urls as $url) {
            try {
                new Request('GET', $url);
                self::fail('Unsafe URL was accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Invalid HTTP request.', $exception->getMessage());
            }
        }

        $this->expectException(InvalidArgumentException::class);
        new Request('GET', 'https://example.test/', ['Authorization' => "Bearer test\r\nX: injected"]);
    }

    /**
     * Validate middleware history independently of Guzzle's version-specific PHPDoc.
     *
     * @return array{request: RequestInterface, options: array<mixed>}
     */
    private static function firstHistoryTransaction(mixed $history): array
    {
        self::assertIsArray($history);
        self::assertCount(1, $history);
        $transaction = $history[0];
        self::assertIsArray($transaction);
        $request = $transaction['request'];
        self::assertInstanceOf(RequestInterface::class, $request);
        $options = $transaction['options'];
        self::assertIsArray($options);

        return ['request' => $request, 'options' => $options];
    }
}
