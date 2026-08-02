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

use GameQ\Exception\ProtocolException;
use JsonException;

/**
 * FiveM info.json protocol.
 *
 * This is used internally by the Cfx protocol to supplement the UDP status
 * response with the server version and selected public variables.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Cfxinfo extends Http
{
    protected array $packets = [
        self::PACKET_STATUS => "GET /info.json HTTP/1.0\r\nAccept: application/json\r\n\r\n",
    ];

    protected string $protocol = 'cfxinfo';

    protected string $name = 'cfxinfo';

    protected string $name_long = 'CitizenFX server information';

    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        if ($this->packets_response === []) {
            return [];
        }

        $response = $this->extractHttpBody(implode('', $this->packets_response), 'Cfx');

        try {
            $decoded = json_decode(trim($response), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ProtocolException(__METHOD__ . ' JSON info response from Cfx is invalid.', 0, $exception);
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new ProtocolException(__METHOD__ . ' JSON info response from Cfx must be an object.');
        }

        return $this->normalizeStringKeyedArray($decoded);
    }
}
