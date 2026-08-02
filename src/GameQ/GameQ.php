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

use GameQ\Exception\ProtocolException;
use GameQ\Exception\QueryException;
use GameQ\Exception\ServerException;
use GameQ\Filters\Base;
use GameQ\Query\Core;
use GameQ\Query\Native;
use InvalidArgumentException;
use JsonException;
use LogicException;

/**
 * Base GameQ Class
 *
 * This class should be the only one that is included when you use GameQ to query
 * any games servers.
 *
 * Requirements: See README for the current PHP and extension requirements.
 *
 * @author Austin Bischoff <austin@codebeard.com>
 *
 * @property bool        $debug
 * @property string|null $capture_packets_file
 * @property int         $stream_timeout
 * @property int         $timeout
 * @property int         $write_wait
 * @property int         $max_follow_up_rounds
 * @property int         $max_servers_per_batch
 */
class GameQ
{
    /**
     * Holds the instance of itself
     *
     * @var self
     */
    protected static GameQ $instance;

    /**
     * Create a new instance of this class
     */
    public static function factory(): self
    {
        self::$instance = new self();

        return self::$instance;
    }

    /* Dynamic Section */

    /**
     * Default options
     *
     * @var array<string, mixed>
     */
    protected array $options = [
        'debug'                => false,
        'timeout'              => 3, // Seconds
        'filters'              => [
            // Default normalize
            'normalize_d751713988987e9331980363e24189ce' => [
                'filter'  => 'normalize',
                'options' => [],
            ],
        ],
        // Advanced settings
        'stream_timeout'       => 200000, // See http://www.php.net/manual/en/function.stream-select.php for more info
        // How long (in micro-seconds) to pause between writing to server sockets, helps cpu usage
        'write_wait'           => 500,
        'max_servers_per_batch' => 50,

        // Used for generating protocol test data
        'capture_packets_file' => null,
    ];

    /**
     * Array of servers being queried
     *
     * @var array<string, Server>
     */
    protected array $servers = [];

    /**
     * The query library to use.  Default is Native
     *
     * @var class-string<Core>
     */
    protected string $queryLibrary = Native::class;

    /**
     * Holds the instance of the queryLibrary
     */
    protected ?Core $query = null;

    /**
     * Get an option's value
     */
    public function __get(string $option): mixed
    {
        return $this->options[$option] ?? null;
    }

    /**
     * Set an option's value
     */
    public function __set(string $option, mixed $value): void
    {
        $this->options[$option] = $value;
    }

    /**
     * Determine whether an option has been configured.
     */
    public function __isset(string $option): bool
    {
        return isset($this->options[$option]);
    }

    /**
     * @return array<string, Server>
     */
    public function getServers(): array
    {
        return $this->servers;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Chainable call to __set, uses set as the actual setter
     */
    public function setOption(string $var, mixed $value): self
    {
        $this->__set($var, $value);

        return $this;
    }

    /**
     * Add a single server
     *
     * @param array<string, mixed> $server_info
     *
     * @throws ServerException
     */
    public function addServer(array $server_info = []): self
    {
        // Add and validate the server
        $this->servers[uniqid('', true)] = new Server($server_info);

        return $this;
    }

    /**
     * Add multiple servers in a single call
     *
     * @param array<array-key, array<string, mixed>> $servers
     *
     * @throws ServerException
     */
    public function addServers(array $servers = []): self
    {
        // Loop through all the servers and add them
        foreach ($servers as $server_info) {
            $this->addServer($server_info);
        }

        return $this;
    }

    /**
     * Add a set of servers from a file or an array of files.
     * Supported formats:
     * JSON
     *
     * @param string|list<string> $files
     *
     * @throws ServerException
     */
    public function addServersFromFiles(string|array $files = []): self
    {
        // Since we expect an array let us turn a string (i.e. single file) into an array
        if (!is_array($files)) {
            $files = [$files];
        }

        // Iterate over the file(s) and add them
        foreach ($files as $file) {
            // Check to make sure the file exists and we can read it
            if (!file_exists($file) || !is_readable($file)) {
                continue;
            }

            // See if this file is JSON
            $contents = file_get_contents($file);

            if ($contents === false) {
                continue;
            }

            try {
                $servers = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            if (!is_array($servers)) {
                continue;
            }

            $validServers = [];

            foreach ($servers as $serverInfo) {
                if (is_array($serverInfo)) {
                    $validServer = array_filter($serverInfo, is_string(...), ARRAY_FILTER_USE_KEY);

                    $validServers[] = $validServer;
                }
            }

            // Add this list of servers
            $this->addServers($validServers);
        }

        return $this;
    }

    /**
     * Clear all the defined servers
     */
    public function clearServers(): self
    {
        $this->servers = [];

        return $this;
    }

    /**
     * Add a filter to the processing list
     *
     * @param array<string, mixed> $options
     * @throws InvalidArgumentException
     */
    public function addFilter(string $filterName, array $options = []): self
    {
        // Create the filter hash so we can run multiple versions of the same filter
        try {
            $encodedOptions = json_encode($options, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Filter options must be JSON-serializable.', 0, $exception);
        }

        $filterHash = sprintf('%s_%s', strtolower($filterName), md5($encodedOptions));

        // Add the filter
        $filters = $this->listFilters();
        $filters[$filterHash] = [
            'filter'  => strtolower($filterName),
            'options' => $options,
        ];
        $this->options['filters'] = $filters;

        unset($filterHash);

        return $this;
    }

    /**
     * Remove an added filter
     */
    public function removeFilter(string $filterHash): self
    {
        // Make lower case
        $filterHash = strtolower($filterHash);

        // Remove this filter if it has been defined
        $filters = $this->listFilters();

        if (array_key_exists($filterHash, $filters)) {
            unset($filters[$filterHash]);
            $this->options['filters'] = $filters;
        }

        return $this;
    }

    /**
     * Return the list of applied filters
     *
     * @return array<string, array{filter: string, options: array<string, mixed>}>
     */
    public function listFilters(): array
    {
        $filters = $this->options['filters'] ?? null;

        if (!is_array($filters)) {
            return [];
        }

        $validFilters = [];

        foreach ($filters as $hash => $filter) {
            if (
                is_string($hash)
                && is_array($filter)
                && isset($filter['filter'], $filter['options'])
                && is_string($filter['filter'])
                && is_array($filter['options'])
            ) {
                $validOptions = array_filter($filter['options'], is_string(...), ARRAY_FILTER_USE_KEY);

                $validFilters[$hash] = [
                    'filter' => $filter['filter'],
                    'options' => $validOptions,
                ];
            }
        }

        return $validFilters;
    }

    /**
     * Main method used to actually process all the added servers and return the information
     *
     * @return array<string, array<string, mixed>>
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws QueryException
     * @throws ProtocolException
     */
    public function process(): array
    {
        $queryLibrary = $this->queryLibrary;
        $this->query = new $queryLibrary();

        // Define the return
        $results = [];

        $servers = $this->servers;
        $batchSize = max(1, $this->getIntegerOption('max_servers_per_batch', 50));

        try {
            foreach (array_chunk($servers, $batchSize, true) as $batch) {
                $this->servers = $batch;

                // Do server challenge(s) first, if any
                $this->doChallenges();

                // Do packets for server(s) and get query responses
                $this->doQueries();

                // Now we should have some information to process for each server
                foreach ($batch as $server) {
                    // Parse the responses for this server
                    $result = $this->doParseResponse($server);

                    // Apply the filters
                    foreach ($this->doApplyFilters($result, $server) as $key => $value) {
                        $result[$key] = $value;
                    }

                    // Sort the keys so they are alphabetical and nicer to look at
                    ksort($result);

                    // Add the result to the results array
                    $results[$server->id()] = $result;
                }
            }
        } finally {
            $this->servers = $servers;
        }

        return $results;
    }

    /**
     * Do server challenge, where required
     *
     * @throws QueryException
     * @throws ProtocolException
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    protected function doChallenges(): void
    {
        $query = $this->getQuery();

        // Initialize the sockets for reading
        $sockets = [];

        // By default, we don't have any challenges to process
        $server_challenge = false;

        // Do challenge packets
        foreach ($this->servers as $server_id => $server) {
            // This protocol has a challenge packet that needs to be sent
            if ($server->protocolInstance()->hasChallenge()) {
                // We have a challenge, set the flag
                $server_challenge = true;

                // Let's make a clone of the query class
                $socket = clone $query;

                // Set the information for this query socket
                $socket->set(
                    $server->protocolInstance()->transport(),
                    $server->ip(),
                    $server->portQuery(),
                    $this->getIntegerOption('timeout', 3),
                );

                try {
                    // Now write the challenge packet to the socket.
                    $challengePacket = $server->protocolInstance()->getPacket(Protocol::PACKET_CHALLENGE);

                    if (!is_string($challengePacket)) {
                        throw new ProtocolException('The challenge packet must be a string.');
                    }
                    $socket->write($challengePacket);

                    // Add the socket information so we can reference it easily
                    $sockets[(int) $socket->get()] = [
                        'server_id' => $server_id,
                        'socket'    => $socket,
                    ];
                } catch (QueryException $exception) {
                    // Check to see if we are in debug, if so bubble up the exception
                    if ($this->isDebugEnabled()) {
                        throw $exception;
                    }
                }

                unset($socket);

                // Let's sleep shortly so we are not hammering out calls rapid fire style hogging cpu
                usleep($this->getIntegerOption('write_wait', 500));
            }
        }

        // We have at least one server with a challenge, we need to listen for responses
        if ($server_challenge) {
            // Now we need to listen for and grab challenge response(s)
            $responses = $query->getResponses(
                $sockets,
                $this->getIntegerOption('timeout', 3),
                $this->getIntegerOption('stream_timeout', 200000),
            );

            // Iterate over the challenge responses
            foreach ($responses as $socket_id => $response) {
                // Back out the server_id we need to update the challenge response for
                $server_id = $sockets[$socket_id]['server_id'];

                // Make this into a buffer so it is easier to manipulate
                $challenge = new Buffer(implode('', $response));

                // Grab the server instance
                $server = $this->servers[$server_id];

                // Apply the challenge
                $server->protocolInstance()->challengeParseAndApply($challenge);

                // Add this socket to be reused, has to be reused in GameSpy3 for example
                $server->socketAdd($sockets[$socket_id]['socket']);

                // Clear
                unset($server);
            }

            // Challenge sockets with no response cannot be reused by the actual query.
            foreach ($sockets as $socketId => $socketInfo) {
                if (!array_key_exists($socketId, $responses)) {
                    $socketInfo['socket']->close();
                }
            }
        }
    }

    /**
     * Run the actual queries and get the response(s)
     *
     * @throws ProtocolException
     * @throws QueryException
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    protected function doQueries(): void
    {
        $query = $this->getQuery();

        // Initialize the array of sockets
        $sockets = [];

        // Iterate over the server list
        foreach ($this->servers as $server_id => $server) {
            /* @var $server Server */

            // Invoke the beforeSend method
            $server->protocolInstance()->beforeSend($server);

            // Get all the non-challenge packets we need to send
            $packets = $server->protocolInstance()->getPacket('!' . Protocol::PACKET_CHALLENGE);

            if (!is_array($packets)) {
                $packets = [$packets];
            }

            if ($packets === []) {
                // Skip nothing else to do for some reason.
                continue;
            }

            // Try to use an existing socket
            if (($socket = $server->socketGet()) === null) {
                // Let's make a clone of the query class
                $socket = clone $query;

                // Set the information for this query socket
                $socket->set(
                    $server->protocolInstance()->transport(),
                    $server->ip(),
                    $server->portQuery(),
                    $this->getIntegerOption('timeout', 3),
                );
            }

            try {
                // Iterate over all the packets we need to send
                foreach ($packets as $packet_data) {
                    // Now write the packet to the socket.
                    $socket->write($packet_data);

                    // Let's sleep shortly so we are not hammering out calls rapid fire style
                    usleep($this->getIntegerOption('write_wait', 500));
                }

                unset($packets);

                // Add the socket information so we can reference it easily
                $sockets[(int) $socket->get()] = [
                    'server_id' => $server_id,
                    'socket'    => $socket,
                ];
            } catch (QueryException $exception) {
                // Check to see if we are in debug, if so bubble up the exception
                if ($this->isDebugEnabled()) {
                    throw $exception;
                }

                continue;
            }

            // Clean up the sockets, if any left over
            $server->socketCleanse();
        }

        // Now we need to listen for and grab response(s)
        $responses = $query->getResponses(
            $sockets,
            $this->getIntegerOption('timeout', 3),
            $this->getIntegerOption('stream_timeout', 200000),
        );

        // Iterate over the responses
        foreach ($responses as $socket_id => $response) {
            // Back out the server_id
            $server_id = $sockets[$socket_id]['server_id'];

            // Grab the server instance
            $server = $this->servers[$server_id];

            // Save the response from this packet
            $server->protocolInstance()->packetResponse($response);

            unset($server);
        }

        $this->doFollowUpQueries($query, $sockets);

        // Now we need to close all the sockets
        foreach ($sockets as $socketInfo) {
            /* @var $socket Core */
            $socket = $socketInfo['socket'];

            // Close the socket
            $socket->close();

            unset($socket);
        }

        unset($sockets);
    }

    /**
     * Allow protocols to request response-driven follow-up packets, such as paginated results.
     *
     * @param array<int, array{server_id: string, socket: Core}> $sockets
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws QueryException
     */
    private function doFollowUpQueries(Core $query, array $sockets): void
    {
        $maxRounds = max(0, $this->getIntegerOption('max_follow_up_rounds', 64));

        for ($round = 0; $round < $maxRounds; ++$round) {
            $followUpSockets = [];

            foreach ($sockets as $socketId => $socketInfo) {
                $server = $this->servers[$socketInfo['server_id']] ?? null;

                if ($server === null) {
                    continue;
                }

                $packets = $server->protocolInstance()->getFollowUpPackets();

                if ($packets === []) {
                    continue;
                }

                try {
                    foreach ($packets as $packet) {
                        $socketInfo['socket']->write($packet);
                        usleep($this->getIntegerOption('write_wait', 500));
                    }
                } catch (QueryException $exception) {
                    if ($this->isDebugEnabled()) {
                        throw $exception;
                    }

                    continue;
                }

                $followUpSockets[$socketId] = $socketInfo;
            }

            if ($followUpSockets === []) {
                return;
            }

            $responses = $query->getResponses(
                $followUpSockets,
                $this->getIntegerOption('timeout', 3),
                $this->getIntegerOption('stream_timeout', 200000),
            );

            if ($responses === []) {
                return;
            }

            foreach ($responses as $socketId => $response) {
                $socketInfo = $followUpSockets[$socketId] ?? null;

                if ($socketInfo === null) {
                    continue;
                }

                $server = $this->servers[$socketInfo['server_id']] ?? null;
                $server?->protocolInstance()->appendPacketResponse($response);
            }
        }
    }

    /**
     * Parse the response for a specific server
     *
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ProtocolException
     */
    protected function doParseResponse(Server $server): array
    {
        try {
            // We want to save this server's response to a file (useful for unit testing)
            $captureFile = $this->getCapturePacketsFile();

            if ($captureFile !== null) {
                file_put_contents(
                    $captureFile,
                    implode(PHP_EOL . '||' . PHP_EOL, $server->protocolInstance()->packetResponse()),
                );
            }

            // Get the server response
            $results = $server->protocolInstance()->processResponse();

            // Check for online before we do anything else
            $results['gq_online'] = (count($results) > 0);
        } catch (ProtocolException $exception) {
            // Check to see if we are in debug, if so bubble up the exception
            if ($this->isDebugEnabled()) {
                throw $exception;
            }

            // We ignore this server
            $results = [
                'gq_online' => false,
            ];
        }

        // Now add some default stuff
        $results['gq_address'] = $results['gq_address'] ?? $server->ip();
        $results['gq_port_client'] = $server->portClient();
        $results['gq_port_query'] = $results['gq_port_query']
            ?? $server->portQuery();
        $results['gq_protocol'] = $server->protocolInstance()->getProtocol();
        $results['gq_type'] = (string) $server->protocolInstance();
        $results['gq_name'] = $server->protocolInstance()->nameLong();
        $results['gq_transport'] = $server->protocolInstance()->transport();

        // Process the join link
        if (!isset($results['gq_joinlink']) || $results['gq_joinlink'] === '') {
            $results['gq_joinlink'] = $server->getJoinLink() ?? '';
        }

        return $results;
    }

    /**
     * Apply any filters to the results
     *
     * @param array<string, mixed> $results
     * @return array<string, mixed>
     */
    protected function doApplyFilters(array $results, Server $server): array
    {
        // Loop over the filters
        foreach ($this->listFilters() as $filterOptions) {
            $filterClass = sprintf('GameQ\\Filters\\%s', ucfirst($filterOptions['filter']));

            if (!is_a($filterClass, Base::class, true)) {
                continue;
            }

            $filter = new $filterClass($filterOptions['options']);
            $results = $filter->apply($results, $server);
        }

        return $results;
    }

    /**
     * Return the initialized query implementation.
     *
     * @throws LogicException
     */
    private function getQuery(): Core
    {
        if ($this->query === null) {
            throw new LogicException('The query implementation has not been initialized.');
        }

        return $this->query;
    }

    /**
     * Read an integer option while keeping invalid user input away from socket APIs.
     *
     * @throws InvalidArgumentException
     */
    private function getIntegerOption(string $name, int $default): int
    {
        $value = $this->options[$name] ?? $default;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        throw new InvalidArgumentException("The '$name' option must be an integer.");
    }

    private function isDebugEnabled(): bool
    {
        return ($this->options['debug'] ?? false) === true;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getCapturePacketsFile(): ?string
    {
        $captureFile = $this->options['capture_packets_file'] ?? null;

        if ($captureFile !== null && !is_string($captureFile)) {
            throw new InvalidArgumentException("The 'capture_packets_file' option must be a string or null.");
        }

        return $captureFile;
    }
}
