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

class Eos extends Base
{
    public function testLiveResponsesDoNotRetainAuthenticationData(): void
    {
        $protocol = new class extends \GameQ\Protocols\Eos {
            public ?string $queriedWithToken = null;

            /** @return list<string> */
            public function packetResponsesForTest(): array
            {
                return $this->packets_response;
            }

            protected function authenticate(): string
            {
                return 'access-token';
            }

            protected function queryServers(string $authToken): array
            {
                $this->queriedWithToken = $authToken;

                return [['hostname' => 'EOS server']];
            }
        };
        $server = new \GameQ\Server([
            'type' => 'eos',
            'host' => '127.0.0.1:7777',
        ]);

        $protocol->beforeSend($server);

        self::assertSame('access-token', $protocol->queriedWithToken);
        self::assertSame([], $protocol->packetResponsesForTest());
        self::assertSame('EOS server', $protocol->processResponse()['hostname']);
    }

    public function testCompressedResponsesAreNegotiatedByCurl(): void
    {
        $protocol = new class extends \GameQ\Protocols\Eos {
            protected string $grant_type = 'external_auth';

            protected ?string $deployment_id = 'deployment';

            protected ?string $user_id = 'user';

            protected ?string $user_secret = 'secret';

            /** @var list<list<string>> */
            public array $capturedHeaders = [];

            public function authenticateForTest(): ?string
            {
                return $this->authenticate();
            }

            protected function httpRequest(string $url, array $headers, string $postFields): array
            {
                $this->capturedHeaders[] = $headers;

                return ['access_token' => str_contains($url, '/deviceid') ? 'device-token' : 'access-token'];
            }
        };

        self::assertSame('access-token', $protocol->authenticateForTest());
        self::assertCount(2, $protocol->capturedHeaders);

        foreach ($protocol->capturedHeaders as $headers) {
            self::assertSame([], array_values(array_filter(
                $headers,
                static fn(string $header): bool => str_starts_with(strtolower($header), 'accept-encoding:'),
            )));
        }
    }

    /**
     * Test to ensure the response processing is correct
     *
     * @return void
     */
    public function testResponses(): void
    {
        $result = $this->queryTest(
            '127.0.0.1:27015',
            'eos',
            [],
            false,
            ['skip_http_requests' => true],
        );

        self::assertFalse($result['gq_online']);
    }
}
