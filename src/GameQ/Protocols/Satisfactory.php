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

use GameQ\Buffer;
use GameQ\Exception\ProtocolException;
use GameQ\Protocol;
use GameQ\Result;

/**
 * Satisfactory Lightweight Query Protocol Class
 *
 * @see https://satisfactory.wiki.gg/wiki/Dedicated_servers/Lightweight_Query_API
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Satisfactory extends Protocol
{
    private const MAGIC = 0xF6D5;

    private const PROTOCOL_VERSION = 1;

    private const REQUEST_COOKIE = "\x01\x02\x03\x04\x05\x06\x07\x08";

    /** @var array<int, string> */
    private const SERVER_STATES = [
        0 => 'offline',
        1 => 'idle',
        2 => 'loading',
        3 => 'playing',
    ];

    protected string $protocol = 'satisfactory';

    protected string $name = 'satisfactory';

    protected string $name_long = 'Satisfactory';

    protected array $packets = [
        self::PACKET_STATUS => "\xD5\xF6\x00\x01" . self::REQUEST_COOKIE . "\x01",
    ];

    protected array $normalize = [
        'general' => [
            'dedicated' => 'dedicated',
            'hostname' => 'server_name',
        ],
    ];

    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        if (count($this->packets_response) !== 1) {
            throw new ProtocolException('Satisfactory returned an unexpected number of packets.');
        }

        $buffer = new Buffer($this->packets_response[0]);

        if ($buffer->getLength() < 29 || $buffer->readInt16() !== self::MAGIC) {
            throw new ProtocolException('Satisfactory response has an invalid protocol magic value.');
        }

        if ($buffer->readInt8() !== 1) {
            throw new ProtocolException('Satisfactory response has an invalid message type.');
        }

        if ($buffer->readInt8() !== self::PROTOCOL_VERSION) {
            throw new ProtocolException('Satisfactory response uses an unsupported protocol version.');
        }

        if ($buffer->read(8) !== self::REQUEST_COOKIE) {
            throw new ProtocolException('Satisfactory response does not match the request cookie.');
        }

        $serverState = $buffer->readInt8();
        $serverNetCl = $buffer->readInt32();
        $serverFlags = $buffer->readInt64();
        $subStateCount = $buffer->readInt8();
        $subStates = [];

        for ($index = 0; $index < $subStateCount; ++$index) {
            $subStates[$buffer->readInt8()] = $buffer->readInt16();
        }

        $serverNameLength = $buffer->readInt16();

        if ($buffer->getLength() !== ($serverNameLength + 1)) {
            throw new ProtocolException('Satisfactory response has an invalid server name length.');
        }

        $serverName = $buffer->read($serverNameLength);

        if ($buffer->readInt8() !== 1) {
            throw new ProtocolException('Satisfactory response has an invalid terminator.');
        }

        $result = new Result();
        $result->add('server_name', $serverName);
        $result->add('server_state', $serverState);
        $result->add('server_state_name', self::SERVER_STATES[$serverState] ?? 'unknown');
        $result->add('server_net_cl', $serverNetCl);
        $result->add('server_flags', $serverFlags);
        $result->add('modded', ($serverFlags & 1) === 1);
        $result->add('is_game_running', $serverState === 3);
        $result->add('dedicated', true);

        foreach ($subStates as $id => $version) {
            $result->add('sub_state_' . $id, $version);
        }

        return $result->fetch();
    }
}
