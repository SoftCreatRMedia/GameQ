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

use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

/**
 * Optional Guzzle transport with per-request timeouts and bounded response writes.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
final class GuzzleClient extends Psr18Client
{
    public function __construct(
        private readonly GuzzleClientInterface&PsrClientInterface $guzzle,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
    ) {
        parent::__construct($guzzle, $requestFactory, $streamFactory);
    }

    /**
     * @throws GuzzleException
     * @throws Throwable
     */
    protected function sendRequest(RequestInterface $message, Request $request): ResponseInterface
    {
        $stream = Utils::streamFor();
        $bytes = 0;
        $sink = FnStream::decorate($stream, [
            'write' => static function (string $chunk) use ($stream, $request, &$bytes): int {
                if ($bytes + strlen($chunk) > $request->maxResponseBytes) {
                    throw new HttpException('HTTP response exceeds the size limit.');
                }

                $written = $stream->write($chunk);
                $bytes += $written;

                return $written;
            },
        ]);

        try {
            return $this->guzzle->send($message, [
                'allow_redirects' => false,
                'http_errors' => false,
                'verify' => true,
                'timeout' => $request->timeout,
                'connect_timeout' => $request->timeout,
                'read_timeout' => $request->timeout,
                'decode_content' => true,
                'stream' => false,
                'sink' => $sink,
                'cookies' => false,
            ]);
        } catch (Throwable $exception) {
            $sink->close();

            throw $exception;
        }
    }
}
