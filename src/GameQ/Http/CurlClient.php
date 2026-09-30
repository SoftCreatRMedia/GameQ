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

/**
 * Dependency-free default HTTP transport for standalone GameQ installations.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
final class CurlClient implements ClientInterface
{
    public function send(Request $request): Response
    {
        $handle = curl_init($request->url);

        if ($handle === false) {
            throw new HttpException('Unable to initialize HTTP transport.');
        }

        $body = '';
        $headers = [];
        $headerBytes = 0;
        $protocol = str_starts_with($request->url, 'https://') ? CURLPROTO_HTTPS : CURLPROTO_HTTP;
        $options = [
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_CONNECTTIMEOUT => $request->timeout,
            CURLOPT_TIMEOUT => $request->timeout,
            CURLOPT_PROTOCOLS => $protocol,
            CURLOPT_REDIR_PROTOCOLS => $protocol,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_MAXFILESIZE => $request->maxResponseBytes,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'GameQ',
            CURLOPT_HTTPHEADER => array_map(
                static fn(string $name, string $value): string => "$name: $value",
                array_keys($request->headers),
                array_values($request->headers),
            ),
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, $request): int {
                if (strlen($body) + strlen($chunk) > $request->maxResponseBytes) {
                    return 0;
                }

                $body .= $chunk;

                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers, &$headerBytes): int {
                $headerBytes += strlen($line);

                if ($headerBytes > 65536) {
                    return 0;
                }

                if (str_starts_with($line, 'HTTP/')) {
                    $headers = [];
                } elseif (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($name))][] = trim($value);
                }

                return strlen($line);
            },
        ];

        if ($request->method === 'POST') {
            $options[CURLOPT_POSTFIELDS] = $request->body;
        } elseif ($request->method === 'HEAD') {
            $options[CURLOPT_NOBODY] = true;
        }

        try {
            curl_setopt_array($handle, $options);

            if (curl_exec($handle) !== true) {
                // Do not include URLs, tokens, or upstream response bodies in errors.
                throw new HttpException('HTTP transfer failed.', curl_errno($handle));
            }

            return new Response(curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $body, $headers);
        } finally {
            unset($handle);
        }
    }
}
