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

/**
 * Core for the query mechanisms
 *
 * @author Austin Bischoff <austin@codebeard.com>
 */
abstract class Core
{
    /**
     * The socket used by this resource
     *
     * @var null|resource
     */
    public $socket;

    /**
     * The transport type (udp, tcp, etc...)
     * See http://php.net/manual/en/transports.php for the supported list
      */
    protected ?string $transport;

    /**
     * Connection IP address
     */
    protected ?string $ip = null;

    /**
     * Connection port
     */
    protected ?int $port = null;

    /**
     * The time in seconds to wait before timing out while connecting to the socket
     */
    protected int $timeout = 3; // Seconds

    /**
     * Socket is blocking?
     */
    protected bool $blocking = false;

    /**
     * Called when the class is cloned
     */
    public function __clone()
    {
        // Reset the properties for this class when cloned
        $this->reset();
    }

    /**
     * Set the connection information for the socket
     */
    public function set(?string $transport, string $ip, int $port, int $timeout = 3, bool $blocking = false): void
    {
        $this->transport = $transport;
        $this->ip = $ip;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->blocking = $blocking;
    }

    /**
     * Reset this instance's properties
     */
    public function reset(): void
    {
        $this->transport = null;
        $this->ip = null;
        $this->port = null;
        $this->timeout = 3;
        $this->blocking = false;
    }

    public function getTransport(): ?string
    {
        return $this->transport;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getBlocking(): bool
    {
        return $this->blocking;
    }

    /**
     * Create a new socket
     */
    abstract protected function create(): void;

    /**
     * Get the socket
     *
     * @throws QueryException
     */
    abstract public function get(): mixed;

    /**
     * Write data to the socket
     *
     * @return int The number of bytes written
     * @throws QueryException
     */
    abstract public function write(string|array $data): int;

    /**
     * Close the socket
     */
    abstract public function close(): void;

    /**
     * Read the responses from the socket(s)
     */
    abstract public function getResponses(array $sockets, int $timeout, int $stream_timeout): array;
}
