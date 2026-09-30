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

namespace GameQ\Tests\Fixtures;

use JsonException;
use RuntimeException;

// This fixture is a PHP built-in-server router entry point.
// phpcs:disable PSR1.Files.SideEffects

/**
 * Local HTTP endpoint and proxy fixture for transport regression tests.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
final class HttpRouter
{
    /**
     * @throws JsonException
     */
    public static function respond(): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        if (!is_string($uri)) {
            throw new RuntimeException('The local HTTP request URI must be a string.');
        }

        $path = parse_url($uri, PHP_URL_PATH);

        if ($path === '/redirect') {
            header('Location: /echo', true, 302);

            return;
        }

        if ($path === '/large') {
            echo str_repeat('x', 32);

            return;
        }

        if ($path === '/gzip') {
            header('Content-Encoding: gzip');
            echo gzencode(str_repeat('x', 32));

            return;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'uri' => $uri,
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? '',
            'body' => file_get_contents('php://input'),
        ], JSON_THROW_ON_ERROR);
    }
}

HttpRouter::respond();
