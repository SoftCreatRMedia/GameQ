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
use GameQ\Protocol;
use GameQ\Result;

/**
 * UOX3 Ultima Online Free Shard Protocol Class
 *
 * @see https://github.com/UOX3DevTeam/UOX3/blob/develop/data/js/server/network/0x7f_uogatewayServerPoll.js
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Uox3 extends Protocol
{
    protected string $protocol = 'uox3';

    protected string $name = 'uox3';

    protected string $name_long = 'Ultima Online (UOX3)';

    protected string $transport = self::TRANSPORT_TCP;

    protected array $packets = [
        self::PACKET_STATUS => "\x7F\x00\x00\x01\xF1\x00\x04\xFF",
    ];

    protected array $normalize = [
        'general' => [
            'dedicated' => 'dedicated',
            'hostname' => 'hostname',
            'numplayers' => 'num_players',
        ],
    ];

    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        $response = trim(implode('', $this->packets_response), "\x00\r\n\t ");
        $fields = array_map('trim', explode(',', $response));

        if (count($fields) < 7 || $fields[0] === '' || $fields[1] === '') {
            throw new ProtocolException('UOX3 returned an invalid server poll response.');
        }

        $values = [];

        foreach (array_slice($fields, 2) as $field) {
            $parts = explode('=', $field, 2);

            if (count($parts) === 2) {
                $values[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        $age = $this->numericValue($values, 'age');
        $clients = $this->numericValue($values, 'clients');
        $items = $this->numericValue($values, 'items');
        $characters = $this->numericValue($values, 'chars');
        $memory = $this->numericValue($values, 'mem', 'K');

        $result = new Result();
        $result->add('engine', $fields[0]);
        $result->add('hostname', $fields[1]);
        $result->add('age_hours', $age);
        $result->add('uptime', $age * 3600);
        $result->add('num_players', $clients);
        $result->add('items', $items);
        $result->add('characters', $characters);
        $result->add('memory_kb', $memory);
        $result->add('dedicated', true);

        return $result->fetch();
    }

    /**
     * @param array<string, string> $values
     * @throws ProtocolException
     */
    private function numericValue(array $values, string $key, string $suffix = ''): int
    {
        $value = $values[$key] ?? null;

        if (!is_string($value)) {
            throw new ProtocolException("UOX3 response is missing the '$key' field.");
        }

        if ($suffix !== '') {
            $value = preg_replace('/' . preg_quote($suffix, '/') . '$/i', '', $value) ?? $value;
        }

        if (!ctype_digit($value)) {
            throw new ProtocolException("UOX3 response contains an invalid '$key' field.");
        }

        return (int) $value;
    }
}
