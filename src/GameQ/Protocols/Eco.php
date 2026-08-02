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
use GameQ\Result;
use JsonException;

/**
 * ECO Global Survival Protocol Class
 *
 * @author Austin Bischoff <austin@codebeard.com>
 */
class Eco extends Http
{
    /**
     * Packets to send
     *
     * @var array<string, string>
     */
    protected array $packets = [
        self::PACKET_STATUS => "GET /frontpage HTTP/1.0\r\nAccept: */*\r\n\r\n",
    ];

    /**
     * Http protocol is SSL
     *
     */
    protected string $transport = self::TRANSPORT_TCP;

    /**
     * The protocol being used
     *
     */
    protected string $protocol = 'eco';

    /**
     * String name of this protocol class
     *
     */
    protected string $name = 'eco';

    /**
     * Longer string name of this protocol class
     *
     */
    protected string $name_long = "ECO Global Survival";

    /**
     * query_port = client_port + 1
     */
    protected int $port_diff = 1;

    /**
     * Normalize some items
     */
    protected array $normalize = [
        // General
        'general' => [
            // target       => source
            'dedicated'  => 'dedicated',
            'hostname'   => 'description',
            'maxplayers' => 'totalplayers',
            'numplayers' => 'onlineplayers',
            'password'   => 'haspassword',
        ],
    ];

    /**
     * Process the response
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        if ($this->packets_response === []) {
            return [];
        }

        // Implode and rip out the JSON
        preg_match('/[{](.*)[}]/ms', implode('', $this->packets_response), $matches);

        // Return should be JSON, let's validate
        if (!isset($matches[0])) {
            throw new ProtocolException("JSON response from Eco server is invalid.");
        }

        try {
            $json = json_decode($matches[0], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ProtocolException('JSON response from Eco server is invalid.', 0, $exception);
        }

        $info = is_array($json) ? ($json['Info'] ?? null) : null;

        if (!is_array($info)) {
            throw new ProtocolException("JSON response from Eco server does not contain an 'Info' object.");
        }

        $result = new Result();

        // Server is always dedicated
        $result->add('dedicated', 1);

        foreach ($info as $name => $setting) {
            if (is_string($name)) {
                $result->add(strtolower($name), $setting);
            }
        }

        return $result->fetch();
    }
}
