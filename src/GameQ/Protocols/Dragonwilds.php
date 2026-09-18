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

/**
 * RuneScape: Dragonwilds dedicated-server directory protocol.
 *
 * Dragonwilds publishes dedicated servers through Epic Online Services. The
 * credentials below are the dedicated-server client credentials distributed
 * with the official server and are authorized for redistribution with this
 * implementation.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Dragonwilds extends Eos
{
    /** @var list<string> */
    private const SAFE_NATIVE_ATTRIBUTES = [
        'ADDRESS_s',
        'ADDRESSBOUND_s',
        'BEACONPORT_s',
        'BH_l',
        'CS_l',
        'GAMEMODE_s',
        'MAPNAME_s',
        'MD_l',
        'PC_l',
        'PP_l',
        'REGION_s',
        'SP_l',
        'VN_s',
        'X0_l',
        'XB_l',
        'XD_l',
        'XM_l',
        'XR_l',
        'XS_s',
        'XT_l',
        'XV_l',
        'XX_s',
        '__EOS_BLISTENING_b',
        '__EOS_BUSESPRESENCE_b',
    ];

    protected string $protocol = 'dragonwilds';

    protected string $name = 'dragonwilds';

    protected string $name_long = 'RuneScape: Dragonwilds';

    protected int $state = self::STATE_BETA;

    protected ?string $deployment_id = 'dd10a15a04d945e2950e1e106884e17a';

    protected ?string $user_id = 'xyza7891CtSKDkoM4GKYmSVqMmI5Jkh2';

    protected ?string $user_secret = 'HdpSCYuVXQxrCewQOaGjlJlXCYkjthalEIGnZ6FO3yE';

    /**
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        $session = $this->selectSession($this->getServerSessions());
        $attributes = $this->normalizeStringKeyedArray($session['attributes'] ?? null);
        $settings = $this->normalizeStringKeyedArray($session['settings'] ?? null);

        $hostname = $this->requiredString($attributes, 'VN_s');
        $world = $this->requiredString($attributes, 'XS_s');
        $map = $this->requiredString($attributes, 'MAPNAME_s');
        $players = $this->requiredNonNegativeInteger($session, 'totalPlayers');
        $maxPlayers = $this->requiredNonNegativeInteger($settings, 'maxPublicPlayers');
        $build = $this->requiredNonNegativeInteger($attributes, 'XB_l');
        $passwordMarker = $attributes['XP_l'] ?? null;

        if (
            !is_int($passwordMarker)
            && (!is_string($passwordMarker) || preg_match('/^-?\d+$/D', $passwordMarker) !== 1)
        ) {
            throw new ProtocolException("Dragonwilds session attribute 'XP_l' has an invalid type.");
        }

        if ($players > $maxPlayers) {
            throw new ProtocolException('Dragonwilds returned a player count above its capacity.');
        }

        $result = new Result();
        $result->add('hostname', $hostname);
        $result->add('world', $world);
        $result->add('mapname', $map);
        $result->add('numplayers', $players);
        $result->add('maxplayers', $maxPlayers);
        $result->add('password', !in_array((string) $passwordMarker, ['-2', '18446744073709551614'], true));
        $result->add('version', (string) $build);
        $result->add('dedicated', true);

        foreach (self::SAFE_NATIVE_ATTRIBUTES as $key) {
            $value = $attributes[$key] ?? null;

            if (is_bool($value) || is_int($value) || is_string($value)) {
                $result->add($key, $value);
            }
        }

        return $result->fetch();
    }

    /**
     * @param list<array<string, mixed>> $sessions
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    private function selectSession(array $sessions): array
    {
        $matches = [];

        foreach ($sessions as $session) {
            if ($this->isExactActiveMatch($session)) {
                $matches[] = $session;
            }
        }

        if ($matches === []) {
            throw new ProtocolException('No active Dragonwilds session matches the specified address and port.');
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        $serverGuid = null;
        $newestTimestamp = null;
        $newest = null;
        $hasTimestampTie = false;

        foreach ($matches as $session) {
            $attributes = $this->normalizeStringKeyedArray($session['attributes'] ?? null);
            $candidateGuid = $attributes['XX_s'] ?? null;
            $lastUpdated = $session['lastUpdated'] ?? null;

            if (!is_string($candidateGuid) || $candidateGuid === '' || !is_string($lastUpdated)) {
                throw new ProtocolException('Multiple ambiguous Dragonwilds sessions match the specified port.');
            }

            if ($serverGuid !== null && $serverGuid !== $candidateGuid) {
                throw new ProtocolException('Multiple ambiguous Dragonwilds sessions match the specified port.');
            }

            $serverGuid = $candidateGuid;
            $timestamp = strtotime($lastUpdated);

            if ($timestamp === false) {
                throw new ProtocolException('Multiple Dragonwilds sessions have invalid update timestamps.');
            }

            if ($newestTimestamp === null || $timestamp > $newestTimestamp) {
                $newestTimestamp = $timestamp;
                $newest = $session;
                $hasTimestampTie = false;
            } elseif ($timestamp === $newestTimestamp) {
                $hasTimestampTie = true;
            }
        }

        if ($hasTimestampTie) {
            throw new ProtocolException('Multiple ambiguous Dragonwilds sessions match the specified port.');
        }

        return $newest;
    }

    /**
     * @param array<string, mixed> $session
     */
    private function isExactActiveMatch(array $session): bool
    {
        if (($session['started'] ?? null) !== true) {
            return false;
        }

        $attributes = $this->normalizeStringKeyedArray($session['attributes'] ?? null);
        $advertisedAddress = $attributes['ADDRESS_s'] ?? null;
        $boundAddress = $attributes['ADDRESSBOUND_s'] ?? null;

        if (!is_string($advertisedAddress) || !is_string($boundAddress)) {
            return false;
        }

        if ($advertisedAddress !== trim((string) $this->serverIp, '[]')) {
            return false;
        }

        if (!$this->boundAddressMatches($boundAddress)) {
            return false;
        }

        return ($attributes['X0_l'] ?? null) === 1
            && ($attributes['__EOS_BLISTENING_b'] ?? null) === true;
    }

    private function boundAddressMatches(string $boundAddress): bool
    {
        $separator = strrpos($boundAddress, ':');

        if ($separator === false || $this->serverPortQuery === null) {
            return false;
        }

        $address = trim(substr($boundAddress, 0, $separator), '[]');
        $port = substr($boundAddress, $separator + 1);

        if ($port !== (string) $this->serverPortQuery) {
            return false;
        }

        return in_array($address, ['0.0.0.0', '::', trim((string) $this->serverIp, '[]')], true);
    }

    /**
     * @param array<string, mixed> $values
     * @throws ProtocolException
     */
    private function requiredString(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        if (!is_string($value) || $value === '') {
            throw new ProtocolException("Dragonwilds field '$key' is missing or invalid.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     * @throws ProtocolException
     */
    private function requiredNonNegativeInteger(array $values, string $key): int
    {
        $value = $values[$key] ?? null;

        if (!is_int($value) || $value < 0) {
            throw new ProtocolException("Dragonwilds field '$key' is missing or invalid.");
        }

        return $value;
    }
}
