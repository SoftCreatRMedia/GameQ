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

namespace GameQ\Protocols;

use GameQ\Exception\ProtocolException;
use GameQ\Server;
use JsonException;

/**
 * Epic Online Services Protocol Class
 *
 * Serves as a base class for EOS-powered games.
 *
 * @package GameQ\Protocols
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Eos extends Http
{
    /**
     * The protocol being used
     *
     * @var string
     */
    protected string $protocol = 'eos';

    /**
     * Longer string name of this protocol class
     *
     * @var string
     */
    protected string $name_long = 'Epic Online Services';

    /**
     * String name of this protocol class
     *
     * @var string
     */
    protected string $name = 'eos';

    /**
     * Grant type used for authentication
     *
     * @var string
     */
    protected string $grant_type = 'client_credentials';

    /**
     * Deployment ID for the game or application
     *
     * @var string|null
     */
    protected ?string $deployment_id = null;

    /**
     * User ID for authentication
     *
     * @var string|null
     */
    protected ?string $user_id = null;

    /**
     * User secret key for authentication
     *
     * @var string|null
     */
    protected ?string $user_secret = null;

    /**
     * Holds the server ip so we can overwrite it back
     *
     * @var string|null
     */
    protected ?string $serverIp = null;

    /**
     * Holds the server port query so we can overwrite it back
     *
     * @var int|null
     */
    protected ?int $serverPortQuery = null;

    /** @var list<array<string, mixed>>|null */
    private ?array $queriedSessions = null;

    /**
     * Normalize some items
     *
     * @var array<string, array<string, string|list<string>>>
     */
    protected array $normalize = [
        // General
        'general' => [
            // target       => source
            'hostname'   => 'hostname',
            'mapname'    => 'mapname',
            'maxplayers' => 'maxplayers',
            'numplayers' => 'numplayers',
            'password'   => 'password',
        ],
    ];

    /**
     * Process the response from the EOS API
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        $sessions = $this->getServerSessions();
        $session = reset($sessions);

        if (!is_array($session)) {
            throw new ProtocolException('No server data found. Server might be offline.');
        }

        return $this->normalizeStringKeyedArray($session);
    }

    /**
     * Decode the session list returned by EOS.
     *
     * @return list<array<string, mixed>>
     * @throws ProtocolException
     */
    protected function getServerSessions(): array
    {
        if ($this->queriedSessions !== null) {
            if ($this->queriedSessions === []) {
                throw new ProtocolException('No server data found. Server might be offline.');
            }

            return $this->queriedSessions;
        }

        $index = ($this->grant_type === 'external_auth') ? 2 : 1;

        if (!isset($this->packets_response[$index])) {
            throw new ProtocolException('No server data found. Server might be offline.');
        }

        try {
            $serverData = json_decode($this->packets_response[$index], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ProtocolException('EOS returned invalid JSON server data.', 0, $exception);
        }

        $sessions = is_array($serverData) ? ($serverData['sessions'] ?? null) : null;

        // If no server data, throw an exception
        if (!is_array($sessions) || $sessions === []) {
            throw new ProtocolException('No server data found. Server might be offline.');
        }

        $normalizedSessions = [];

        foreach ($sessions as $session) {
            if (is_array($session)) {
                $normalizedSessions[] = $this->normalizeStringKeyedArray($session);
            }
        }

        if ($normalizedSessions === []) {
            throw new ProtocolException('EOS returned no valid server sessions.');
        }

        return $normalizedSessions;
    }

    /**
     * Called before sending the request
     *
     * @param Server $server
     */
    public function beforeSend(Server $server): void
    {
        $this->queriedSessions = null;
        $this->serverIp = $server->ip();
        $this->serverPortQuery = $server->portQuery();

        if (($this->options['skip_http_requests'] ?? false) === true) {
            return;
        }

        // Authenticate and get the access token
        $authToken = $this->authenticate();

        if ($authToken === null) {
            return;
        }

        // Query for server data
        $this->queriedSessions = $this->queryServers($authToken);
    }

    /**
     * Authenticate to get the access token
     *
     * @return string|null
     */
    protected function authenticate(): ?string
    {
        if ($this->user_id === null || $this->user_secret === null || $this->deployment_id === null) {
            return null;
        }

        $authUrl = "https://api.epicgames.dev/auth/v1/oauth/token";
        $authHeaders = [
            'Authorization: Basic ' . base64_encode($this->user_id . ':' . $this->user_secret),
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $authFields = [
            'grant_type' => $this->grant_type,
            'deployment_id' => $this->deployment_id,
        ];

        if ($this->grant_type === 'external_auth') {
            // Perform device authentication if necessary
            $deviceAuth = $this->deviceAuthentication();

            $deviceAccessToken = $deviceAuth['access_token'] ?? null;

            if (!is_string($deviceAccessToken) || $deviceAccessToken === '') {
                return null;
            }
            $authFields['external_auth_type'] = 'deviceid_access_token';
            $authFields['external_auth_token'] = $deviceAccessToken;
            $authFields['nonce'] = 'ABCHFA3qgUCJ1XTPAoGDEF';
            $authFields['display_name'] = 'User';
        }

        // Make the request to get the access token
        $response = $this->httpRequest(
            $authUrl,
            $authHeaders,
            http_build_query($authFields, '', '&', PHP_QUERY_RFC3986),
        );

        return isset($response['access_token']) && is_string($response['access_token'])
            ? $response['access_token']
            : null;
    }

    /**
     * Query the EOS server for matchmaking data
     *
     * @return list<array<string, mixed>>|null
     */
    protected function queryServers(string $authToken): ?array
    {
        $serverQueryUrl = 'https://api.epicgames.dev/matchmaking/v1/' . $this->deployment_id . '/filter';
        $queryHeaders = [
            'Authorization: Bearer ' . $authToken,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        try {
            $queryBody = json_encode([
                'criteria' => [
                    [
                        'key' => 'attributes.ADDRESS_s',
                        'op' => 'EQUAL',
                        'value' => $this->serverIp,
                    ],
                ],
                'maxResults' => 200,
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $response = $this->httpRequest($serverQueryUrl, $queryHeaders, $queryBody);

        $sessions = $response['sessions'] ?? null;

        if (!is_array($sessions)) {
            return null;
        }

        $normalizedSessions = [];

        foreach ($sessions as $session) {
            if (is_array($session)) {
                $normalizedSessions[] = $this->normalizeStringKeyedArray($session);
            }
        }

        return $normalizedSessions;
    }

    /**
     * Handle device authentication for external auth type
     *
     * @return array<string, mixed>|null
     */
    protected function deviceAuthentication(): ?array
    {
        if ($this->user_id === null || $this->user_secret === null) {
            return null;
        }

        $deviceAuthUrl = "https://api.epicgames.dev/auth/v1/accounts/deviceid";
        $deviceAuthHeaders = [
            'Authorization: Basic ' . base64_encode($this->user_id . ':' . $this->user_secret),
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $deviceAuthPostFields = http_build_query(['deviceModel' => 'PC'], '', '&', PHP_QUERY_RFC3986);

        return $this->httpRequest($deviceAuthUrl, $deviceAuthHeaders, $deviceAuthPostFields);
    }

    /**
     * Execute an HTTP request
     *
     * @param list<string> $headers
     * @return array<string, mixed>|null
     */
    protected function httpRequest(string $url, array $headers, string $postFields): ?array
    {
        if ($url === '') {
            return null;
        }

        $ch = curl_init();

        if ($ch === false) {
            return null;
        }

        $timeout = max(1, $this->normalizeInteger($this->options['http_timeout'] ?? 5, 5));
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_MAXFILESIZE => 8 * 1024 * 1024,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $postFields,
        ]);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if (!is_string($response) || $statusCode < 200 || $statusCode >= 300) {
            return null;
        }

        try {
            $decodedResponse = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return $this->normalizeStringKeyedArray($decodedResponse);
    }

    /**
     * Safely retrieves an attribute from an array or returns a default value.
     *
     * @param array<string, mixed> $attributes
     */
    protected function getAttribute(array $attributes, string $key, mixed $default = null): mixed
    {
        return $attributes[$key] ?? $default;
    }
}
