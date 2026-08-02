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

/**
 * Shared packet grouping for protocols whose response type is encoded in a fixed-length header.
 */
trait GroupedResponseTrait
{
    /**
     * @param list<string> $responses
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    protected function processGroupedResponses(array $responses, int $headerLength): array
    {
        $packets = [];

        foreach ($responses as $response) {
            $buffer = new Buffer($response);
            $header = $buffer->read($headerLength);
            $packets[$header][] = $buffer->getBuffer();
        }

        $resultSets = [];

        foreach ($packets as $header => $packetGroup) {
            if (!array_key_exists($header, $this->responses)) {
                throw new ProtocolException(
                    static::class . "::processResponse response type '" . bin2hex($header) . "' is not valid",
                );
            }

            $resultSets[] = $this->processResponseMethod(
                $this->responses[$header],
                new Buffer(implode($packetGroup)),
            );
        }

        return array_merge(...$resultSets);
    }
}
