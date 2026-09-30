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

use RuntimeException;

/**
 * Disposable local HTTP server used to test real transfers without public APIs.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
final class HttpServer
{
    /** @var resource|null */
    private $process;

    /** @var resource */
    private $log;

    public readonly string $url;

    public function __construct()
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);

        if ($socket === false) {
            throw new RuntimeException('Unable to reserve a local test port.');
        }

        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        if ($address === false) {
            throw new RuntimeException('Unable to read the local test port.');
        }

        $this->url = 'http://' . $address;
        $log = tmpfile();

        if ($log === false) {
            throw new RuntimeException('Unable to create the test server log.');
        }

        $this->log = $log;
        $process = proc_open(
            [PHP_BINARY, '-S', $address, __DIR__ . '/HttpRouter.php'],
            [0 => ['pipe', 'r'], 1 => $log, 2 => $log],
            $pipes,
        );

        if (!is_resource($process)) {
            fclose($log);

            throw new RuntimeException('Unable to start the local test server.');
        }

        $this->process = $process;
        fclose($pipes[0]);
        $deadline = microtime(true) + 5;

        do {
            $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);

            if ($probe !== false) {
                fclose($probe);

                return;
            }

            usleep(10000);
        } while (microtime(true) < $deadline);

        $this->stop();

        throw new RuntimeException('The local test server did not become ready.');
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
            fclose($this->log);
        }
    }
}
