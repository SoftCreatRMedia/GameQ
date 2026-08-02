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

namespace GameQ\Query;

use GameQ\Exception\QueryException;
use GameQ\Protocol;
use Throwable;

/**
 * Native way of querying servers
 *
 * @author Austin Bischoff <austin@codebeard.com>
 */
class Native extends Core
{
    private const MAX_RESPONSE_BYTES_PER_SOCKET = 16 * 1024 * 1024;

    private const MAX_RESPONSE_PACKETS_PER_SOCKET = 1024;

    /**
     * Get the current socket or create one and return
     *
     * @return resource
     * @throws QueryException
     */
    public function get(): mixed
    {
        // No socket for this server, make one
        if (!is_resource($this->socket)) {
            $this->create();
        }

        if (!is_resource($this->socket)) {
            throw new QueryException('The query socket could not be created.');
        }

        return $this->socket;
    }

    /**
     * Write data to the socket
     *
     * @param string|list<string> $data
     *
     * @return int The number of bytes written
     * @throws QueryException
     */
    public function write(string|array $data): int
    {
        try {
            // No socket for this server, make one
            $socket = $this->get();
            $payload = is_array($data) ? implode('', $data) : $data;

            // Send the packet
            // Socket failures are reported through the return value. Suppress the
            // accompanying warning so callers consistently receive QueryException.
            $bytesWritten = @fwrite($socket, $payload);

            if ($bytesWritten === false) {
                throw new QueryException('Unable to write the query packet to the socket.');
            }

            return $bytesWritten;
        } catch (Throwable $exception) {
            if ($exception instanceof QueryException) {
                throw $exception;
            }

            throw new QueryException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }
    }

    /**
     * Close the current socket
     */
    public function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Create a new socket for this query
     *
     * @throws QueryException
     */
    protected function create(): void
    {
        // Create the remote address
        if ($this->transport === null || $this->ip === null || $this->port === null) {
            throw new QueryException('Connection information must be set before creating a socket.');
        }

        $remote_addr = sprintf("%s://%s:%d", $this->transport, $this->ip, $this->port);

        // Create context
        $context = stream_context_create([
            'socket' => [
                'bindto' => '0:0', // Bind to any available IP and OS decided port
            ],
        ]);

        // Define these first
        $errno = 0;
        $errstr = '';

        // Create the socket
        $socket = @stream_socket_client(
            $remote_addr,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($socket !== false) {
            $this->socket = $socket;

            // Set the read timeout on the streams
            stream_set_timeout($this->socket, $this->timeout);

            // Set blocking mode
            stream_set_blocking($this->socket, $this->blocking);

            // Set the read buffer
            stream_set_read_buffer($this->socket, 0);

            // Set the write buffer
            stream_set_write_buffer($this->socket, 0);
        } else {
            // Reset socket
            $this->socket = null;

            // Something bad happened, throw query exception
            $errorCode = $errno ?? 0;

            throw new QueryException(
                __METHOD__ . " - Error creating socket to server $this->ip:$this->port. Error: " . $errstr,
                $errorCode,
            );
        }
    }

    /**
     * Pull the responses out of the stream
     *
     * @param array<int, array{server_id: string, socket: Core}> $sockets
     * @param int $timeout
     * @param int $stream_timeout
     * @return array<int, list<string>>
     *
     * @throws QueryException
     */
    public function getResponses(array $sockets, int $timeout, int $stream_timeout): array
    {
        // Will hold the responses read from the sockets
        $responses = [];

        // To store the sockets
        $sockets_tmp = [];

        // Track sockets that have returned data so completed datagram queries can stop after one idle interval while
        // stream queries remain open long enough to receive delayed chunks.
        $respondedSockets = [];
        $streamSockets = [];
        $streamWaitsForEof = [];
        $streamExpectedBytes = [];
        $idleIntervals = [];
        $responseBytes = [];
        $responsePackets = [];

        // Loop and pull out all the actual sockets we need to listen on
        foreach ($sockets as $socket_id => $socket_data) {
            // Get the socket
            $socket = $socket_data['socket'];

            // Append the actual socket we are listening to
            $sockets_tmp[$socket_id] = $socket->get();
            $streamSockets[$socket_id] = in_array(
                $socket->getTransport(),
                [Protocol::TRANSPORT_TCP, Protocol::TRANSPORT_SSL, Protocol::TRANSPORT_TLS],
                true,
            );

            unset($socket);
        }

        // Init some variables
        $read = $sockets_tmp;
        $write = null;
        $except = null;

        // Check to see if $read is empty, if so stream_select() will throw a warning
        if ($read === []) {
            return $responses;
        }

        // This is when it should stop
        $time_stop = microtime(true) + $timeout;

        // Let's loop until we break something.
        while (microtime(true) < $time_stop) {
            // Check to make sure $read is not empty, if so we are done
            if ($read === []) {
                break;
            }

            // Now lets listen for some streams, but do not cross the streams!
            $streams = stream_select($read, $write, $except, 0, $stream_timeout);

            // A select error is fatal for this read round.
            if ($streams === false) {
                break;
            }

            // A short select interval without data is not the overall query timeout.
            if ($streams === 0) {
                $streamIdleLimit = max(1, (int) ceil(1_000_000 / max(1, $stream_timeout)));
                $overallIdleLimit = max(1, (int) ceil(($timeout * 1_000_000) / max(1, $stream_timeout)));

                foreach ($respondedSockets as $socketId => $_) {
                    if (!array_key_exists($socketId, $sockets_tmp)) {
                        continue;
                    }

                    $idleIntervals[$socketId] = ($idleIntervals[$socketId] ?? 0) + 1;
                    $idleLimit = match (true) {
                        $streamWaitsForEof[$socketId] ?? false => $overallIdleLimit,
                        $streamSockets[$socketId] ?? false => $streamIdleLimit,
                        default => 1,
                    };

                    if ($idleIntervals[$socketId] >= $idleLimit) {
                        unset($sockets_tmp[$socketId]);
                    }
                }

                if ($sockets_tmp === []) {
                    break;
                }

                $read = $sockets_tmp;

                continue;
            }

            // Loop the sockets that received data back
            foreach ($read as $socket) {
                /* @var $socket resource */
                $socketId = (int) $socket;
                $metadata = stream_get_meta_data($socket);
                $drainAvailable = $metadata['blocked'] === false;

                // TLS streams can buffer several decrypted records after the underlying socket becomes readable.
                // Drain everything currently available before waiting in stream_select() again.
                while (true) {
                    $response = fread($socket, 32768);

                    if ($response === false) {
                        break;
                    }

                    if ($response === '') {
                        if (feof($socket)) {
                            unset($sockets_tmp[$socketId]);
                        }

                        break;
                    }

                    $nextByteCount = ($responseBytes[$socketId] ?? 0) + strlen($response);
                    $nextPacketCount = ($responsePackets[$socketId] ?? 0) + 1;

                    if (
                        $nextByteCount > self::MAX_RESPONSE_BYTES_PER_SOCKET
                        || $nextPacketCount > self::MAX_RESPONSE_PACKETS_PER_SOCKET
                    ) {
                        // Never pass a truncated response to a protocol parser.
                        unset($responses[$socketId], $sockets_tmp[$socketId]);

                        break;
                    }

                    // Add the response we got back
                    $responses[$socketId][] = $response;
                    $respondedSockets[$socketId] = true;
                    $idleIntervals[$socketId] = 0;
                    $responseBytes[$socketId] = $nextByteCount;
                    $responsePackets[$socketId] = $nextPacketCount;

                    if (($streamSockets[$socketId] ?? false) && !array_key_exists($socketId, $streamWaitsForEof)) {
                        $responseStart = implode('', $responses[$socketId]);
                        $headerEnd = strpos($responseStart, "\r\n\r\n");

                        if ($headerEnd !== false && str_starts_with($responseStart, 'HTTP/')) {
                            $headers = substr($responseStart, 0, $headerEnd);
                            $streamWaitsForEof[$socketId] = str_starts_with($headers, 'HTTP/1.0')
                                || preg_match('/^Connection:\s*close\s*$/mi', $headers) === 1;

                            if (preg_match('/^Content-Length:\s*(\d+)\s*$/mi', $headers, $matches) === 1) {
                                $streamExpectedBytes[$socketId] = $headerEnd + 4 + (int) $matches[1];
                            }
                        }
                    }

                    if (
                        isset($streamExpectedBytes[$socketId])
                        && $nextByteCount >= $streamExpectedBytes[$socketId]
                    ) {
                        unset($sockets_tmp[$socketId]);

                        break;
                    }

                    if (!$drainAvailable) {
                        break;
                    }
                }
            }

            // Because stream_select modifies read we need to reset it each time to the original array of sockets
            $read = $sockets_tmp;
        }

        // Free up some memory
        unset(
            $streams,
            $read,
            $write,
            $except,
            $sockets_tmp,
            $respondedSockets,
            $streamSockets,
            $streamWaitsForEof,
            $streamExpectedBytes,
            $idleIntervals,
            $responseBytes,
            $responsePackets,
            $time_stop,
            $response,
            $metadata,
            $drainAvailable,
        );

        // Return all the responses, may be empty if something went wrong
        return $responses;
    }
}
