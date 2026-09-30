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
 * HTTP response body, status, and headers returned by a query transport.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
final class Response
{
    /** @param array<list<string>> $headers */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
        public readonly array $headers = [],
    ) {
    }

    /** @return list<string> */
    public function header(string $name): array
    {
        foreach ($this->headers as $key => $values) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $values;
            }
        }

        return [];
    }

    /** Preserve the HTTP envelope expected by existing protocol parsers. */
    public function toPacket(): string
    {
        return "HTTP/1.1 $this->statusCode\r\n\r\n$this->body";
    }
}
