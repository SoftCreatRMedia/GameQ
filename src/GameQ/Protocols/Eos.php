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

/**
 * Epic Online Services Protocol Class
 *
 * Serves as a base class for EOS-powered games.
 *
 * @package GameQ\Protocols
 * @author  H.Rouatbi
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
     * @var string
     */
    protected ?string $deployment_id = null;

    /**
     * User ID for authentication
     *
     * @var string
     */
    protected ?string $user_id = null;

    /**
     * User secret key for authentication
     *
     * @var string
     */
    protected ?string $user_secret = null;

    /**
     * Holds the server ip so we can overwrite it back
     *
     * @var string
     */
    protected ?string $serverIp = null;

    /**
     * Holds the server port query so we can overwrite it back
     *
     * @var int
     */
    protected ?int $serverPortQuery = null;

    /**
     * Normalize some items
     *
     * @var array
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
     * @return array
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        $index = ($this->grant_type === 'external_auth') ? 2 : 1;
        $serverData = isset($this->packets_response[$index])
            ? json_decode($this->packets_response[$index], true)
            : null;

        $serverData = is_array($serverData) && isset($serverData['sessions']) && is_array($serverData['sessions'])
            ? $serverData['sessions']
            : null;

        // If no server data, throw an exception
        if (empty($serverData)) {
            throw new ProtocolException('No server data found. Server might be offline.');
        }

        return $serverData;
    }

    /**
     * Called before sending the request
     *
     * @param Server $server
     */
    public function beforeSend(Server $server): void
    {
        $this->serverIp = $server->ip();
        $this->serverPortQuery = $server->portQuery();

        if ($this->options['skip_http_requests'] ?? false) {
            return;
        }

        // Authenticate and get the access token
        $authToken = $this->authenticate();

        if ($authToken === null) {
            return;
        }

        // Query for server data
        $this->queryServers($authToken);
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
            'Authorization: Basic ' . base64_encode("{$this->user_id}:{$this->user_secret}"),
            'Accept-Encoding: deflate, gzip',
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $authPostFields = "grant_type={$this->grant_type}&deployment_id={$this->deployment_id}";

        if ($this->grant_type === 'external_auth') {
            // Perform device authentication if necessary
            $deviceAuth = $this->deviceAuthentication();
            if ($deviceAuth === null || !isset($deviceAuth['access_token'])) {
                return null;
            }
            $authPostFields .= "&external_auth_type=deviceid_access_token"
                . "&external_auth_token={$deviceAuth['access_token']}"
                . "&nonce=ABCHFA3qgUCJ1XTPAoGDEF&display_name=User";
        }

        // Make the request to get the access token
        $response = $this->httpRequest($authUrl, $authHeaders, $authPostFields);

        return isset($response['access_token']) && is_string($response['access_token'])
            ? $response['access_token']
            : null;
    }

    /**
     * Query the EOS server for matchmaking data
     *
     * @return array|null
     */
    protected function queryServers(string $authToken): ?array
    {
        $serverQueryUrl = "https://api.epicgames.dev/matchmaking/v1/{$this->deployment_id}/filter";
        $queryHeaders = [
            "Authorization: Bearer {$authToken}",
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $queryBody = json_encode([
            'criteria' => [
                [
                    'key' => 'attributes.ADDRESS_s',
                    'op' => 'EQUAL',
                    'value' => $this->serverIp,
                ],
            ],
            'maxResults' => 200,
        ]);

        if ($queryBody === false) {
            return null;
        }

        $response = $this->httpRequest($serverQueryUrl, $queryHeaders, $queryBody);

        return isset($response['sessions']) && is_array($response['sessions'])
            ? $response['sessions']
            : null;
    }

    /**
     * Handle device authentication for external auth type
     *
     * @return array|null
     */
    protected function deviceAuthentication(): ?array
    {
        if ($this->user_id === null || $this->user_secret === null) {
            return null;
        }

        $deviceAuthUrl = "https://api.epicgames.dev/auth/v1/accounts/deviceid";
        $deviceAuthHeaders = [
            'Authorization: Basic ' . base64_encode("{$this->user_id}:{$this->user_secret}"),
            'Accept-Encoding: deflate, gzip',
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $deviceAuthPostFields = "deviceModel=PC";

        return $this->httpRequest($deviceAuthUrl, $deviceAuthHeaders, $deviceAuthPostFields);
    }

    /**
     * Execute an HTTP request
     *
     * @param string[] $headers
     * @return array|null
     */
    protected function httpRequest(string $url, array $headers, string $postFields): ?array
    {
        $ch = curl_init();

        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $postFields,
        ]);

        $response = curl_exec($ch);

        if (!is_string($response)) {
            return null;
        }

        $this->packets_response[] = $response;

        $decodedResponse = json_decode($response, true);

        return is_array($decodedResponse) ? $decodedResponse : null;
    }

    /**
     * Safely retrieves an attribute from an array or returns a default value.
     *
     * @param array $attributes
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getAttribute(array $attributes, string $key, mixed $default = null): mixed
    {
        return $attributes[$key] ?? $default;
    }
}
