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
 */

namespace GameQ\Protocols;

use GameQ\Buffer;
use GameQ\Exception\ProtocolException;

/**
 * Shared response-envelope processing for the Quake 2 and Quake 3 protocols.
 *
 * @internal
 */
trait QuakeResponseTrait
{
    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        $buffer = new Buffer(implode('', $this->packets_response));
        $header = $buffer->readString("\x0A");

        if ($header === '' || !array_key_exists($header, $this->responses)) {
            throw new ProtocolException(static::class . "::processResponse response type '" . bin2hex($header) . "' is not valid");
        }

        return $this->processResponseMethod($this->responses[$header], $buffer);
    }

    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    protected function processStatus(Buffer $buffer): array
    {
        $results = $this->processServerInfo(new Buffer($buffer->readString("\x0A")));

        return array_merge(
            $results,
            $this->processPlayers(new Buffer($buffer->getBuffer())),
        );
    }

    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    abstract protected function processServerInfo(Buffer $buffer): array;

    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    abstract protected function processPlayers(Buffer $buffer): array;
}
