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

namespace GameQ\Protocols;

use GameQ\Protocol;
use GameQ\Server;

/**
 * SCUM master-server protocol.
 *
 * SCUM server information is obtained from the master-list proxies used by the
 * in-game server browser. The master response is shared by all SCUM protocol
 * instances in the current PHP process.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Scum extends Protocol
{
    /** @var list<array{string, int}> */
    private const DEFAULT_MASTER_SERVERS = [
        ['proxy-1.scum.masterserver.info', 405],
        ['proxy-2.scum.masterserver.info', 405],
        ['proxy-3.scum.masterserver.info', 405],
    ];

    private const MASTER_QUERY = "LST\x00\x00";

    private const MAX_MASTER_RESPONSE_LENGTH = 16 * 1024 * 1024;

    protected string $protocol = 'scum';

    protected string $name = 'scum';

    protected string $name_long = 'SCUM';

    protected string $transport = self::TRANSPORT_TCP;

    protected ?string $join_link = 'steam://connect/%s:%d/';

    protected array $normalize = [
        'general' => [
            'dedicated' => 'dedicated',
            'hostname' => 'hostname',
            'maxplayers' => 'maxplayers',
            'mod' => 'mod',
            'numplayers' => 'numplayers',
        ],
    ];

    /** @var array<string, string|null> */
    private static array $masterResponses = [];

    /** @var array<string, mixed>|null */
    private ?array $serverData = null;

    public function beforeSend(Server $server): void
    {
        $this->serverData = null;
        $response = $this->options['master_response'] ?? null;

        if (!is_string($response)) {
            $masterServers = $this->getMasterServers();
            $cacheKey = hash('sha256', serialize($masterServers));

            if (!array_key_exists($cacheKey, self::$masterResponses)) {
                self::$masterResponses[$cacheKey] = $this->queryMasterServers($masterServers);
            }

            $response = self::$masterResponses[$cacheKey];
        }

        if ($response === null || strlen($response) > self::MAX_MASTER_RESPONSE_LENGTH) {
            return;
        }

        $this->serverData = $this->findServer($response, $server);
    }

    /**
     * @return array<string, mixed>
     */
    public function processResponse(): array
    {
        return $this->serverData ?? [];
    }

    /**
     * @return list<array{string, int}>
     */
    private function getMasterServers(): array
    {
        $configured = $this->options['master_servers'] ?? self::DEFAULT_MASTER_SERVERS;

        if (is_string($configured)) {
            $configuredLines = preg_split('/\R/', $configured);
            $configured = $configuredLines !== false ? $configuredLines : [];
        }

        if (!is_array($configured)) {
            return [];
        }

        $masterServers = [];

        foreach ($configured as $masterServer) {
            $normalized = $this->normalizeMasterServer($masterServer);

            if ($normalized !== null) {
                $masterServers[$normalized[0] . ':' . $normalized[1]] = $normalized;
            }
        }

        return array_values($masterServers);
    }

    /**
     * @return array{string, int}|null
     */
    private function normalizeMasterServer(mixed $masterServer): ?array
    {
        $host = null;
        $port = null;

        if (is_string($masterServer)) {
            $parts = parse_url('tcp://' . trim($masterServer));

            if (is_array($parts)) {
                $host = $parts['host'] ?? null;
                $port = $parts['port'] ?? null;
            }
        } elseif (is_array($masterServer)) {
            $host = $masterServer['host'] ?? $masterServer[0] ?? null;
            $port = $masterServer['port'] ?? $masterServer[1] ?? null;
        }

        if (!is_string($host) || $host === '') {
            return null;
        }

        $port = $this->normalizeInteger($port);

        if ($port < 1 || $port > 65535) {
            return null;
        }

        if (
            filter_var($host, FILTER_VALIDATE_IP) === false
            && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
        ) {
            return null;
        }

        return [$host, $port];
    }

    /**
     * @param list<array{string, int}> $masterServers
     */
    private function queryMasterServers(array $masterServers): ?string
    {
        $timeout = min(30, max(1, $this->normalizeInteger($this->options['master_timeout'] ?? 5, 5)));

        foreach ($masterServers as [$host, $port]) {
            $errorCode = 0;
            $errorMessage = '';
            $formattedHost = str_contains($host, ':') ? '[' . trim($host, '[]') . ']' : $host;
            $socket = @stream_socket_client(
                "tcp://$formattedHost:$port",
                $errorCode,
                $errorMessage,
                $timeout,
                STREAM_CLIENT_CONNECT,
            );

            if (!is_resource($socket)) {
                continue;
            }

            try {
                stream_set_timeout($socket, $timeout);

                if (@fwrite($socket, self::MASTER_QUERY) !== strlen(self::MASTER_QUERY)) {
                    continue;
                }

                $response = $this->readMasterResponse($socket);

                if ($response !== null) {
                    return $response;
                }
            } finally {
                fclose($socket);
            }
        }

        return null;
    }

    /**
     * @param resource $socket
     */
    private function readMasterResponse(mixed $socket): ?string
    {
        $response = '';
        $expectedLength = null;

        while (!feof($socket)) {
            $chunk = fread($socket, 32768);

            if ($chunk === false) {
                return null;
            }

            if ($chunk === '') {
                break;
            }

            if (strlen($response) + strlen($chunk) > self::MAX_MASTER_RESPONSE_LENGTH) {
                return null;
            }

            $response .= $chunk;

            if ($expectedLength === null && strlen($response) >= 4) {
                $lengthData = unpack('Vlength', substr($response, 0, 4));
                $payloadLength = $lengthData['length'] ?? null;

                if (!is_int($payloadLength) || $payloadLength > self::MAX_MASTER_RESPONSE_LENGTH - 4) {
                    return null;
                }

                $expectedLength = $payloadLength + 4;
            }

            if ($expectedLength !== null && strlen($response) >= $expectedLength) {
                return strlen($response) === $expectedLength ? $response : null;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findServer(string $response, Server $server): ?array
    {
        $ip = trim($server->ip(), '[]');
        $responseLength = strlen($response);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false || $responseLength < 4) {
            return null;
        }

        $lengthData = unpack('Vlength', substr($response, 0, 4));

        if (($lengthData['length'] ?? null) !== $responseLength - 4) {
            return null;
        }

        $ipBytes = array_map(intval(...), explode('.', $ip));
        $address = pack('C4', ...array_reverse($ipBytes)) . pack('v', $server->portClient());
        $position = 4;
        $serverData = null;

        // Records have mixed layouts. Only record boundaries may contain a server address;
        // searching arbitrary bytes can mistake a hostname or another field for a record.
        while ($position < $responseLength) {
            $record = $this->getRecordLayout($response, $position);

            if ($record === null) {
                return null;
            }

            if ($serverData === null && substr($response, $position, 6) === $address) {
                $serverData = $this->parseServer($response, $position, $record['long'], $ip, $server->portClient());
            }

            $position = $record['end'];
        }

        return $serverData;
    }

    /**
     * @return array{long: bool, end: int}|null
     */
    private function getRecordLayout(string $response, int $position): ?array
    {
        if (strlen($response) < $position + 30 || substr($response, $position + 7, 2) !== "\xAD\xDE") {
            return null;
        }

        $shortEnd = $this->getRecordEnd($response, $position, 30);
        $longEnd = $this->getRecordEnd($response, $position, 34);

        if ($shortEnd === null && $longEnd === null) {
            return null;
        }

        // Both candidates can end at a valid terminator, especially with 35-byte
        // names. The extension signature and padding then select the long layout.
        if (
            $longEnd !== null
            && ($shortEnd === null || (substr($response, $position + 10, 2) === "\x53\x01" && $response[$position + 15] === "\x00"))
        ) {
            return ['long' => true, 'end' => $longEnd];
        }

        return ['long' => false, 'end' => $shortEnd];
    }

    private function getRecordEnd(string $response, int $position, int $nameOffset): ?int
    {
        $responseLength = strlen($response);

        if ($responseLength < $position + $nameOffset) {
            return null;
        }

        $nameLength = ord($response[$position + $nameOffset - 1]);
        $terminatorPosition = $position + $nameOffset + $nameLength;

        if ($responseLength >= $terminatorPosition + 3 && substr($response, $terminatorPosition, 3) === "\x00\xEF\xBE") {
            return $terminatorPosition + 3;
        }

        // Very old builds omit the leading NUL from the record terminator.
        if ($responseLength >= $terminatorPosition + 2 && substr($response, $terminatorPosition, 2) === "\xEF\xBE") {
            return $terminatorPosition + 2;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseServer(string $response, int $position, bool $long, string $ip, int $port): ?array
    {
        $fieldShift = $long ? 4 : 0;
        $playerShift = $long ? 3 : 0;
        $nameLength = ord($response[$position + 29 + $fieldShift]);
        $timeData = unpack('gtime', substr($response, $position + 13 + $fieldShift, 4));
        $tpsData = unpack('gtps', substr($response, $position + 17 + $fieldShift, 4));
        $versionData = unpack('Vbuild/Cminor/Cmajor', substr($response, $position + 23 + $fieldShift, 6));
        $playstyleData = unpack('cplaystyle', $response[$position + 12 + $fieldShift]);
        $time = $timeData['time'] ?? null;
        $tps = $tpsData['tps'] ?? null;
        $build = $versionData['build'] ?? null;
        $minor = $versionData['minor'] ?? null;
        $major = $versionData['major'] ?? null;
        $playstyle = $playstyleData['playstyle'] ?? null;

        if (
            !is_int($major)
            || !is_int($minor)
            || !is_int($build)
            || !is_int($playstyle)
            || !is_float($time)
            || !is_finite($time)
            || !is_float($tps)
            || !is_finite($tps)
        ) {
            return null;
        }

        $hours = (int) floor($time);
        $minutes = (int) floor(($time - $hours) * 60);
        $hostname = trim(substr($response, $position + 30 + $fieldShift, $nameLength));
        $formattedTime = sprintf('%02d:%02d', $hours, $minutes);
        $version = sprintf(
            '1.%d.%d.%d.%d',
            $major,
            $minor,
            $build >> 24,
            ($build & 0xFFFFFF) >> 4,
        );

        return [
            'dedicated' => true,
            'hostname' => $hostname,
            'ip' => $ip,
            'maxplayers' => ord($response[$position + 10 + $playerShift]),
            'mod' => 'scum',
            'numplayers' => ord($response[$position + 9 + $playerShift]),
            'flags' => ord($response[$position + 11 + $playerShift]),
            'playstyle' => $playstyle,
            'port' => $port,
            'scum_name' => $hostname,
            'scum_record_format' => $long ? 'long' : 'short',
            'scum_time' => $formattedTime,
            'server_class' => ord($response[$position + 6]),
            'time' => $formattedTime,
            'tps' => $tps,
            'version' => $version,
        ];
    }
}
