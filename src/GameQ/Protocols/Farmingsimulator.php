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
use GameQ\Result;
use GameQ\Server;
use JsonException;
use SimpleXMLElement;
use Throwable;

/**
 * Farming Simulator dedicated server web statistics protocol.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Farmingsimulator extends Http
{
    protected string $protocol = 'farmingsimulator';

    protected string $name = 'farmingsimulator';

    protected string $name_long = 'Farming Simulator';

    protected string $responseFormat = 'json';

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);

        $webCode = is_string($options['web_code'] ?? null) ? $options['web_code'] : '';
        $query = http_build_query(['code' => $webCode], '', '&', PHP_QUERY_RFC3986);

        $this->packets = [
            self::PACKET_STATUS => sprintf(
                "GET /feed/dedicated-server-stats.%s?%s HTTP/1.0\r\n"
                . "Accept: application/%s\r\n"
                . "Connection: close\r\n\r\n",
                $this->responseFormat,
                $query,
                $this->responseFormat,
            ),
        ];
    }

    public function beforeSend(Server $server): void
    {
        $webPort = $this->normalizeInteger($this->options['web_port'] ?? null);

        if ($webPort >= 1 && $webPort <= 65535) {
            $server->port_query = $webPort;
        }
    }

    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        if ($this->packets_response === []) {
            return [];
        }

        $body = $this->extractHttpBody(implode('', $this->packets_response), 'Farming Simulator');

        return $this->responseFormat === 'xml'
            ? $this->processXmlResponse($body)
            : $this->processJsonResponse($body);
    }

    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    private function processJsonResponse(string $body): array
    {
        try {
            $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ProtocolException('JSON response from Farming Simulator is invalid.', 0, $exception);
        }

        if (!is_array($response) || array_is_list($response)) {
            throw new ProtocolException('JSON response from Farming Simulator must be an object.');
        }

        $response = $this->normalizeStringKeyedArray($response);
        $server = $this->normalizeStringKeyedArray($response['server'] ?? null);
        $slots = $this->normalizeStringKeyedArray($response['slots'] ?? null);

        if ($server === [] || $slots === []) {
            throw new ProtocolException('JSON response from Farming Simulator is missing server or slot data.');
        }

        $maxPlayers = $this->normalizeInteger($slots['capacity'] ?? null);

        // The web API remains reachable while the game server is stopped, but reports zero capacity.
        if ($maxPlayers === 0) {
            return [];
        }

        $players = $this->processJsonPlayers($slots['players'] ?? null);
        $mods = $this->normalizeRecords($response['mods'] ?? null);
        $response['mods'] = $mods;

        return $this->buildResult(
            $response,
            $server,
            $slots,
            $players,
            $maxPlayers,
            'used',
            ['fs_map_size' => $server['mapSize'] ?? null],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function processJsonPlayers(mixed $value): array
    {
        $players = [];

        if (!is_array($value)) {
            return $players;
        }

        foreach ($value as $playerData) {
            $player = $this->normalizeStringKeyedArray($playerData);

            if ($player === [] || !$this->normalizeBoolean($player['isUsed'] ?? null)) {
                continue;
            }

            $players[] = $player;
        }

        return $players;
    }

    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    private function processXmlResponse(string $body): array
    {
        $previousUseInternalErrors = libxml_use_internal_errors(true);

        try {
            $response = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } catch (Throwable $exception) {
            throw new ProtocolException('XML response from Farming Simulator is invalid.', 0, $exception);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }

        if (!$response instanceof SimpleXMLElement || $response->getName() !== 'Server') {
            throw new ProtocolException('XML response from Farming Simulator must contain a Server document.');
        }

        $server = $this->xmlAttributes($response);
        $slotsElement = $response->Slots;

        if (!$slotsElement instanceof SimpleXMLElement) {
            throw new ProtocolException('XML response from Farming Simulator is missing slot data.');
        }

        $slots = $this->xmlAttributes($slotsElement);
        $maxPlayers = $this->normalizeInteger($slots['capacity'] ?? null);

        if ($maxPlayers === 0) {
            return [];
        }

        $players = [];

        foreach ($slotsElement->Player as $playerElement) {
            if (!$this->normalizeBoolean($this->xmlAttributes($playerElement)['isUsed'] ?? null)) {
                continue;
            }

            $players[] = ['name' => (string) $playerElement];
        }

        return $this->buildResult(
            $this->xmlChildrenToArray($response),
            $server,
            $slots,
            $players,
            $maxPlayers,
            'numUsed',
            ['fs_server_region' => $server['server'] ?? null],
        );
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $server
     * @param array<string, mixed> $slots
     * @param list<array<string, mixed>> $players
     * @param non-empty-string $usedSlotsKey
     * @param array<string, mixed> $additionalFields
     * @return array<string, mixed>
     */
    private function buildResult(
        array $raw,
        array $server,
        array $slots,
        array $players,
        int $maxPlayers,
        string $usedSlotsKey,
        array $additionalFields,
    ): array {
        $result = new Result();

        foreach ($raw as $key => $value) {
            $result->add($key, $value);
        }

        $result->add('dedicated', 1);
        $result->add('gametype', $server['game'] ?? null);
        $result->add('hostname', $server['name'] ?? null);
        $result->add('mapname', $server['mapName'] ?? null);
        $result->add('maxplayers', $maxPlayers);
        $result->add('numplayers', $this->normalizeInteger($slots[$usedSlotsKey] ?? null));
        $result->add('players', $players);
        $result->add('version', $server['version'] ?? null);
        $result->add('fs_day_time', $server['dayTime'] ?? null);
        $result->add('fs_map_overview_filename', $server['mapOverviewFilename'] ?? null);

        foreach ($additionalFields as $key => $value) {
            $result->add($key, $value);
        }

        $result->add('fs_money', $server['money'] ?? null);

        return $result->fetch();
    }

    /**
     * @return array<string, string>
     */
    private function xmlAttributes(SimpleXMLElement $element): array
    {
        $attributes = [];

        foreach ($element->attributes() as $key => $value) {
            $attributes[$key] = (string) $value;
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function xmlChildrenToArray(SimpleXMLElement $element): array
    {
        $result = [];

        foreach ($element->children() as $name => $child) {
            $value = $this->xmlElementToValue($child);

            if (!array_key_exists($name, $result)) {
                $result[$name] = $value;

                continue;
            }

            if (!is_array($result[$name]) || !array_is_list($result[$name])) {
                $result[$name] = [$result[$name]];
            }

            $result[$name][] = $value;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|string
     */
    private function xmlElementToValue(SimpleXMLElement $element): array|string
    {
        $value = $this->xmlChildrenToArray($element);
        $attributes = $this->xmlAttributes($element);

        if ($value === []) {
            $text = (string) $element;

            if ($attributes === []) {
                return $text;
            }

            $value = ['value' => $text];
        }

        foreach ($attributes as $key => $attribute) {
            $value[$key] = $attribute;
        }

        return $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeRecords(mixed $value): array
    {
        $records = [];

        if (!is_array($value)) {
            return $records;
        }

        foreach ($value as $record) {
            $normalizedRecord = $this->normalizeStringKeyedArray($record);

            if ($normalizedRecord !== []) {
                $records[] = $normalizedRecord;
            }
        }

        return $records;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return is_string($value) && in_array(strtolower($value), ['1', 'true'], true);
    }
}
