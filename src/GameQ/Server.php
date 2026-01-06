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

namespace GameQ;

use GameQ\Exception\ServerException;
use GameQ\Query\Core;

/**
 * Server class to represent each server entity
 *
 * @author Austin Bischoff <austin@codebeard.com>
 */
class Server
{
    /*
     * Server array keys
     */
    public const SERVER_TYPE = 'type';
    public const SERVER_HOST = 'host';
    public const SERVER_ID = 'id';
    public const SERVER_OPTIONS = 'options';

    // Server options keys

    /*
     * Use this option when the query_port and client connect ports are different
     */
    public const SERVER_OPTIONS_QUERY_PORT = 'query_port';

    /**
     * The protocol class for this server
     */
    protected ?Protocol $protocol = null;

    /**
     * Id of this server
     */
    public ?string $id = null;

    /**
     * IP Address of this server
     */
    public ?string $ip = null;

    /**
     * The server's client port (connect port)
     */
    public ?int $port_client = null;

    /**
     * The server's query port
     */
    public ?int $port_query = null;

    /**
     * Holds other server specific options
     */
    protected array $options = [];

    /**
     * Holds the sockets already open for this server
     */
    protected array $sockets = [];

    /**
     * Construct the class with the passed options
     *
     * @throws ServerException
     */
    public function __construct(array $server_info = [])
    {
        // Check for server type
        if (!array_key_exists(self::SERVER_TYPE, $server_info) || empty($server_info[self::SERVER_TYPE])) {
            throw new ServerException("Missing server info key '" . self::SERVER_TYPE . "'!");
        }

        // Check for server host
        if (!array_key_exists(self::SERVER_HOST, $server_info) || empty($server_info[self::SERVER_HOST])) {
            throw new ServerException("Missing server info key '" . self::SERVER_HOST . "'!");
        }

        // IP address and port check
        $this->checkAndSetIpPort($server_info[self::SERVER_HOST]);

        // Check for server id
        if (array_key_exists(self::SERVER_ID, $server_info) && !empty($server_info[self::SERVER_ID])) {
            // Set the server id
            $this->id = $server_info[self::SERVER_ID];
        } else {
            // Make an id so each server has an id when returned
            $this->id = sprintf('%s:%d', $this->ip, $this->port_client);
        }

        // Check and set server options
        if (array_key_exists(self::SERVER_OPTIONS, $server_info)) {
            // Set the options
            $this->options = $server_info[self::SERVER_OPTIONS];
        }

        try {
            // Make the protocol class for this type
            $class = new \ReflectionClass(
                sprintf('GameQ\\Protocols\\%s', ucfirst(strtolower($server_info[self::SERVER_TYPE])))
            );

            $this->protocol = $class->newInstanceArgs([$this->options]);
        } catch (\ReflectionException) {
            throw new ServerException("Unable to locate Protocols class for '{$server_info[self::SERVER_TYPE]}'!");
        }

        // Check and set any server options
        $this->checkAndSetServerOptions();

        unset($class);
    }

    /**
     * Check and set the ip address for this server
     *
     * @throws ServerException
     */
    protected function checkAndSetIpPort(string $ip_address): void
    {
        // Test for IPv6
        if (substr_count($ip_address, ':') > 1) {
            // See if we have a port, input should be in the format [::1]:27015 or similar
            if (str_contains($ip_address, ']:')) {
                // Explode to get port
                $server_addr = explode(':', $ip_address);

                // Port is the last item in the array, remove it and save
                $this->port_client = (int)array_pop($server_addr);

                // The rest is the address, recombine
                $this->ip = implode(':', $server_addr);

                unset($server_addr);
            } else {
                // Just the IPv6 address, no port defined, fail
                throw new ServerException(
                    "The host address '$ip_address' is missing the port.  All "
                    . "servers must have a port defined!"
                );
            }

            // Now let's validate the IPv6 value sent, remove the square brackets ([]) first
            if (!filter_var(trim($this->ip, '[]'), FILTER_VALIDATE_IP, ['flags' => FILTER_FLAG_IPV6,])) {
                throw new ServerException("The IPv6 address '$this->ip' is invalid.");
            }
        } else {
            // We have IPv4 with a port defined
            if (str_contains($ip_address, ':')) {
                $addressParts = explode(':', $ip_address);
                $this->ip = $addressParts[0];
                $this->port_client = (int)$addressParts[1];
            } else {
                // No port, fail
                throw new ServerException(
                    "The host address '$ip_address' is missing the port. All "
                    . "servers must have a port defined!"
                );
            }

            // Validate the IPv4 value, if FALSE is not a valid IP, maybe a hostname.
            if (!filter_var($this->ip, FILTER_VALIDATE_IP, ['flags' => FILTER_FLAG_IPV4,])) {
                // Try to resolve the hostname to IPv4
                $resolved = gethostbyname($this->ip);

                // When gethostbyname() fails it returns the original string
                if ($this->ip === $resolved) {
                    // so if ip and the result from gethostbyname() are equal this failed.
                    throw new ServerException("Unable to resolve the host '$this->ip' to an IP address.");
                }

                $this->ip = $resolved;
            }
        }
    }

    /**
     * Check and set any server specific options
     */
    protected function checkAndSetServerOptions(): void
    {
        // Specific query port defined
        if (array_key_exists(self::SERVER_OPTIONS_QUERY_PORT, $this->options)) {
            $this->port_query = (int)$this->options[self::SERVER_OPTIONS_QUERY_PORT];
        } else {
            // Do math based on the protocol class
            $this->port_query = $this->protocol->findQueryPort($this->port_client);
        }
    }

    /**
     * Set an option for this server
     *
     * @return $this
     */
    public function setOption(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Return set option value
     */
    public function getOption(string $key): mixed
    {

        return $this->options[$key] ?? null;
    }

    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Get the ID for this server
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Get the IP address for this server
     */
    public function ip(): string
    {
        return $this->ip;
    }

    /**
     * Get the client port for this server
     */
    public function portClient(): int
    {
        return $this->port_client;
    }

    /**
     * Get the query port for this server
     */
    public function portQuery(): int
    {
        return $this->port_query;
    }

    /**
     * Return the protocol class for this server
     */
    public function protocol(): ?Protocol
    {
        return $this->protocol;
    }

    /**
     * Get the join link for this server
     */
    public function getJoinLink(): ?string
    {
        $joinLink = $this->protocol?->joinLink();

        if ($joinLink === null) {
            return null;
        }

        return sprintf($joinLink, $this->ip, $this->portClient());
    }

    // Socket holding

    /**
     * Add a socket for this server to be reused
     */
    public function socketAdd(Core $socket): void
    {
        $this->sockets[] = $socket;
    }

    /**
     * Get a socket from the list to reuse, if any are available
     */
    public function socketGet(): ?Core
    {
        $socket = null;

        if (count($this->sockets) > 0) {
            $socket = array_pop($this->sockets);
        }

        return $socket;
    }

    /**
     * Clear any sockets still listed and attempt to close them
     */
    public function socketCleanse(): void
    {

        // Close all the sockets available
        foreach ($this->sockets as $socket) {
            /* @var $socket Core */
            $socket->close();
        }

        // Reset the sockets list
        $this->sockets = [];
    }
}
