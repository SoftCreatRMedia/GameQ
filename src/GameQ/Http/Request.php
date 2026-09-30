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

use InvalidArgumentException;

/**
 * Validated HTTP request with transport timeout and response size constraints.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
final class Request
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers = [],
        public readonly string $body = '',
        public readonly int $timeout = 5,
        public readonly int $maxResponseBytes = 8 * 1024 * 1024,
    ) {
        $parts = parse_url($url);

        if ($parts === false) {
            throw new InvalidArgumentException('Invalid HTTP request.');
        }

        if (
            isset($parts['pass'])
            || $maxResponseBytes < 1
            || $timeout < 1
            || isset($parts['user'])
            || ($parts['host'] ?? '') === ''
            || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || preg_match('/[\x00-\x20\x7f]/', $url) === 1
            || !in_array($method, ['GET', 'POST', 'HEAD'], true)
        ) {
            throw new InvalidArgumentException('Invalid HTTP request.');
        }

        foreach ($headers as $name => $value) {
            if (preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/D', $name) !== 1 || preg_match('/[\r\n\x00]/', $value) === 1) {
                throw new InvalidArgumentException('Invalid HTTP request header.');
            }
        }
    }
}
