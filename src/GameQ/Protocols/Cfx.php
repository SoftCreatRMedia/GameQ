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

use GameQ\Buffer;
use GameQ\Exception\ProtocolException;
use GameQ\Exception\QueryException;
use GameQ\Exception\ServerException;
use GameQ\GameQ;
use GameQ\Protocol;
use GameQ\Result;
use GameQ\Server;

/**
 * GTA Five M Protocol Class
 *
 * Server base can be found at https://fivem.net/
 *
 * Based on code found at https://github.com/LiquidObsidian/fivereborn-query
 *
 * @author Austin Bischoff <austin@codebeard.com>
 *
 * Adding FiveM Player List by
 * @author Jesse Lukas <eranio@g-one.org>
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Cfx extends Protocol
{
    /**
     * Array of packets we want to look up.
     * Each key should correspond to a defined method in this or a parent class
     */
    protected array $packets = [
        self::PACKET_STATUS => "\xFF\xFF\xFF\xFFgetinfo xxx",
    ];

    /**
     * Use the response flag to figure out what method to run
     *
     */
    protected array $responses = [
        "\xFF\xFF\xFF\xFFinfoResponse" => "processStatus",
    ];

    /**
     * The query protocol used to make the call
     */
    protected string $protocol = 'cfx';

    /**
     * String name of this protocol class
     */
    protected string $name = 'cfx';

    /**
     * Longer string name of this protocol class
     */
    protected string $name_long = "CitizenFX";

    /**
     * Holds the player list so we can overwrite it back
     *
     * @var list<array<string, mixed>>
     */
    protected array $playerList = [];

    /** @var array<string, mixed> */
    private array $infoData = [];

    /**
     * Normalize settings for this protocol
     */
    protected array $normalize = [
        // General
        'general' => [
            // target       => source
            'gametype'   => 'gametype',
            'hostname'   => 'hostname',
            'mapname'    => 'mapname',
            'maxplayers' => 'sv_maxclients',
            'mod'        => 'gamename',
            'numplayers' => 'clients',
            'password'   => 'privateClients',
        ],
    ];

    /**
     * Get the FiveM player list and info.json data using HTTP subqueries.
     */
    public function beforeSend(Server $server): void
    {
        $webPort = $this->normalizeInteger($this->options['web_port'] ?? $server->portQuery(), $server->portQuery());

        if ($webPort < 1 || $webPort > 65535) {
            $webPort = $server->portQuery();
        }

        $host = $server->ip();

        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            $host = "[$host]";
        }

        $webAddress = "$host:$webPort";

        try {
            $gameQ = new GameQ();
            $gameQ->addServers([
                [
                    Server::SERVER_ID => 'players',
                    Server::SERVER_TYPE => 'cfxplayers',
                    Server::SERVER_HOST => $webAddress,
                ],
                [
                    Server::SERVER_ID => 'info',
                    Server::SERVER_TYPE => 'cfxinfo',
                    Server::SERVER_HOST => $webAddress,
                ],
            ]);
            $results = $gameQ->process();
        } catch (ProtocolException | QueryException | ServerException) {
            return;
        }

        $playerResult = $results['players'] ?? null;

        if (is_array($playerResult)) {
            $players = $playerResult['players'] ?? [];

            if (is_array($players)) {
                $this->playerList = [];

                foreach ($players as $player) {
                    if (!is_array($player)) {
                        continue;
                    }

                    $normalizedPlayer = array_filter($player, is_string(...), ARRAY_FILTER_USE_KEY);
                    $this->playerList[] = $normalizedPlayer;
                }
            }
        }

        $infoResult = $results['info'] ?? null;

        if (is_array($infoResult) && ($infoResult['gq_online'] ?? false) === true) {
            $this->infoData = $this->normalizeStringKeyedArray($infoResult);
        }
    }

    /**
     * Process the response
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        if ($this->packets_response === [] && $this->infoData !== []) {
            $result = new Result();
            $result->add('dedicated', true);
            $result->add('gamename', 'CitizenFX');
            $result->add('clients', count($this->playerList));
            $this->addHttpData($result);

            return $result->fetch();
        }

        // In case it comes back as multiple packets (it shouldn't)
        $buffer = new Buffer(implode('', $this->packets_response));

        // Figure out what packet response this is for
        $response_type = $buffer->readString(PHP_EOL);

        // Figure out which packet response this is
        if ($response_type === '' || !array_key_exists($response_type, $this->responses)) {
            throw new ProtocolException(__METHOD__ . " response type '$response_type' is not valid");
        }

        // Offload the call
        return $this->processResponseMethod($this->responses[$response_type], $buffer);
    }

    /*
     * Internal methods
     */

    /**
     * Handle processing the status response
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    protected function processStatus(Buffer $buffer): array
    {
        // Set the result to a new result instance
        $result = new Result();

        // Let's peek and see if the data starts with a \
        if ($buffer->lookAhead() === '\\') {
            // Burn the first one
            $buffer->skip();
        }

        // Explode the data
        $data = explode('\\', $buffer->getBuffer());

        $itemCount = count($data);

        // Now lets loop the array
        for ($x = 0; $x + 1 < $itemCount; $x += 2) {
            // Set some local vars
            $key = $data[$x];
            $val = $data[$x + 1];

            if ($key === 'challenge') {
                continue; // skip
            }

            // Regular variable so just add the value.
            $result->add($key, $val);
        }

        $this->addHttpData($result);

        return $result->fetch();
    }

    private function addHttpData(Result $result): void
    {
        if ($this->playerList !== []) {
            $result->add('players', $this->playerList);
        }

        if ($this->infoData !== []) {
            $vars = $this->normalizeStringKeyedArray($this->infoData['vars'] ?? null);
            $version = $this->infoData['version'] ?? null;

            if (is_scalar($version)) {
                $result->add('version', (string) $version);
            }

            $discord = $vars['Discord'] ?? $vars['discord'] ?? null;
            $locale = $vars['locale'] ?? null;

            if (is_scalar($discord)) {
                $result->add('fivem_discord', (string) $discord);
            }

            if (is_scalar($locale)) {
                $result->add('fivem_locale', (string) $locale);
            }

            if ($result->get('sv_maxclients') === null) {
                $maxClients = $vars['sv_maxClients'] ?? $vars['sv_maxclients'] ?? null;

                if (is_scalar($maxClients)) {
                    $result->add('sv_maxclients', (string) $maxClients);
                }
            }
        }
    }
}
