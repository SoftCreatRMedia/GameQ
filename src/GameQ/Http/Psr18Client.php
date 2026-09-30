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

namespace GameQ\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

/**
 * Adapter for PSR-18/PSR-17 clients. Configure TLS verification, redirect refusal
 * and timeouts on the supplied client; PSR-18 has no per-request transport options.
 * Response reads are bounded, but the supplied client may buffer before returning.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Psr18Client implements ClientInterface
{
    public function __construct(
        private readonly PsrClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function send(Request $request): Response
    {
        $message = $this->requestFactory->createRequest($request->method, $request->url);

        foreach ($request->headers as $name => $value) {
            $message = $message->withHeader($name, $value);
        }

        $message = $message->withBody($this->streamFactory->createStream($request->body));

        try {
            $response = $this->sendRequest($message, $request);
            $stream = $response->getBody();

            try {
                $size = $stream->getSize();

                if ($size !== null && $size > $request->maxResponseBytes) {
                    throw new HttpException('HTTP response exceeds the size limit.');
                }

                $body = '';

                while (true) {
                    $chunk = $stream->read(min(8192, $request->maxResponseBytes - strlen($body) + 1));

                    if ($chunk === '') {
                        if ($stream->eof()) {
                            break;
                        }

                        throw new HttpException('HTTP response stream stalled.');
                    }

                    if (strlen($body) + strlen($chunk) > $request->maxResponseBytes) {
                        throw new HttpException('HTTP response exceeds the size limit.');
                    }

                    $body .= $chunk;
                }

                $headers = array_map(
                    static fn(array $values): array => array_values($values),
                    $response->getHeaders(),
                );

                return new Response($response->getStatusCode(), $body, $headers);
            } finally {
                $stream->close();
            }
        } catch (ClientExceptionInterface $exception) {
            throw new HttpException('HTTP transfer failed.', 0, $exception);
        } catch (RuntimeException $exception) {
            throw new HttpException('HTTP transfer failed.', 0, $exception);
        }
    }

    /**
     * @throws ClientExceptionInterface
     */
    protected function sendRequest(RequestInterface $message, Request $request): ResponseInterface
    {
        return $this->client->sendRequest($message);
    }
}
