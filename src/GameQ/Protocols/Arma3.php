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
use GameQ\Result;

/**
 * Class Armed Assault 3
 *
 * Rules protocol reference: https://community.bistudio.com/wiki/Arma_3_ServerBrowserProtocol2
 *
 * @package GameQ\Protocols
 * @author  Austin Bischoff <austin@codebeard.com>
 * @author  Memphis017 <https://github.com/Memphis017>
 */
class Arma3 extends Source
{
    // Base DLC names
    private const BASE_DLC_KART      = 'Karts';
    private const BASE_DLC_MARKSMEN  = 'Marksmen';
    private const BASE_DLC_HELI      = 'Helicopters';
    private const BASE_DLC_CURATOR   = 'Curator';
    private const BASE_DLC_EXPANSION = 'Expansion';
    private const BASE_DLC_JETS      = 'Jets';
    private const BASE_DLC_ORANGE    = 'Laws of War';
    private const BASE_DLC_ARGO      = 'Malden';
    private const BASE_DLC_TACOPS    = 'Tac-Ops';
    private const BASE_DLC_TANKS     = 'Tanks';
    private const BASE_DLC_CONTACT   = 'Contact';
    private const BASE_DLC_ENOCH     = 'Contact (Platform)';

    // Special
    private const BASE_DLC_AOW       = 'Art of War';

    // Creator DLC names
    /*
    private const CREATOR_DLC_GM     = 'Global Mobilization';
    private const CREATOR_DLC_VN     = 'S.O.G. Prairie Fire';
    private const CREATOR_DLC_CSLA   = 'ČSLA - Iron Curtain';
    private const CREATOR_DLC_WS     = 'Western Sahara';
    */

    /**
     * DLC Flags/Bits as defined in the documentation.
     *
     * @var array<int, string>
     * @see https://community.bistudio.com/wiki/Arma_3:_ServerBrowserProtocol3
     */
    protected array $dlcFlags = [
        0b0000000000000001 => self::BASE_DLC_KART,
        0b0000000000000010 => self::BASE_DLC_MARKSMEN,
        0b0000000000000100 => self::BASE_DLC_HELI,
        0b0000000000001000 => self::BASE_DLC_CURATOR,
        0b0000000000010000 => self::BASE_DLC_EXPANSION,
        0b0000000000100000 => self::BASE_DLC_JETS,
        0b0000000001000000 => self::BASE_DLC_ORANGE,
        0b0000000010000000 => self::BASE_DLC_ARGO,
        0b0000000100000000 => self::BASE_DLC_TACOPS,
        0b0000001000000000 => self::BASE_DLC_TANKS,
        0b0000010000000000 => self::BASE_DLC_CONTACT,
        0b0000100000000000 => self::BASE_DLC_ENOCH,
        0b0001000000000000 => self::BASE_DLC_AOW,
        0b0010000000000000 => 'Unknown',
        0b0100000000000000 => 'Unknown',
        0b1000000000000000 => 'Unknown',
    ];

    /**
     * String name of this protocol class
     */
    protected string $name = 'arma3';

    /**
     * Longer string name of this protocol class
     */
    protected string $name_long = "Arma3";

    /**
     * Query port = client_port + 1
     */
    protected int $port_diff = 1;

    /**
     * Process the rules since Arma3 changed their response for rules
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    protected function processRules(Buffer $buffer): array
    {
        // Total number of packets, burn it
        $buffer->readInt16();

        // Will hold the data string
        $data = '';

        // Loop until we run out of strings
        while ($buffer->getLength()) {
            // Burn the delimiters (i.e. \x01\x04\x00)
            $buffer->readString();

            // Add the data to the string, we are reassembling it
            $data .= $buffer->readString();
        }

        // Restore escaped sequences
        $data = str_replace(["\x01\x01", "\x01\x02", "\x01\x03"], ["\x01", "\x00", "\xFF"], $data);

        // Make a new buffer with the reassembled data
        $responseBuffer = new Buffer($data);

        unset($data);

        // Set the result to a new result instance
        $result = new Result();

        // Get results
        $result->add('rules_protocol_version', $responseBuffer->readInt8()); // read protocol version
        $result->add('overflow', $responseBuffer->readInt8()); // Read overflow flags
        $dlcByte = $responseBuffer->readInt8(); // Grab DLC byte 1 and use it later
        $dlcByte2 = $responseBuffer->readInt8(); // Grab DLC byte 2 and use it later
        $dlcBits = ($dlcByte2 << 8) | $dlcByte; // concatenate DLC bits to 16 Bit int

        // Grab difficulty so we can man handle it...
        $difficulty = $responseBuffer->readInt8();

        // Process difficulty
        $result->add('3rd_person', $difficulty >> 7);
        $result->add('advanced_flight_mode', ($difficulty >> 6) & 1);
        $result->add('difficulty_ai', ($difficulty >> 3) & 3);
        $result->add('difficulty_level', $difficulty & 3);

        unset($difficulty);

        // Crosshair
        $result->add('crosshair', $responseBuffer->readInt8());

        // Loop over the base DLC bits so we can pull in the info for the DLC (if enabled)
        foreach ($this->dlcFlags as $dlcFlag => $dlcName) {
            // Check that the DLC bit is enabled
            if (($dlcBits & $dlcFlag) === $dlcFlag) {
                // Add the DLC to the list
                $result->addSub('dlcs', 'name', $dlcName);
                $result->addSub('dlcs', 'hash', dechex($responseBuffer->readInt32()));
            }
        }

        // Read the mount of mods, these include DLC as well as Creator DLC and custom modifications
        $modCount = $responseBuffer->readInt8();

        // Add mod count
        $result->add('mod_count', $modCount);

        // Loop over the mods
        while ($modCount) {
            // Read the mods hash
            $result->addSub('mods', 'hash', dechex($responseBuffer->readInt32()));

            // Get the information byte containing DLC flag and steamId length
            $infoByte = $responseBuffer->readInt8();

            // Determine isDLC by flag, first bit in upper nibble
            $result->addSub('mods', 'dlc', ($infoByte & 0b00010000) === 0b00010000);

            // Read the steam id of the mod/CDLC (might be less than 4 bytes)
            $result->addSub('mods', 'steam_id', $responseBuffer->readInt32($infoByte & 0x0F));

            // Read the name of the mod
            $modName = $responseBuffer->readPascalString(0, true);
            $result->addSub('mods', 'name', $modName !== '' ? $modName : 'Unknown');

            --$modCount;
        }

        // No longer needed
        unset($dlcByte, $dlcByte2, $dlcBits);

        // Get the signatures count
        $signatureCount = $responseBuffer->readInt8();
        $result->add('signature_count', $signatureCount);

        // Make signatures array
        $signatures = [];

        // Loop until we run out of signatures
        for ($x = 0; $x < $signatureCount; $x++) {
            $signatures[] = $responseBuffer->readPascalString(0, true);
        }

        // Add as a simple array
        $result->add('signatures', $signatures);

        unset($responseBuffer, $signatureCount, $signatures, $x);

        return $result->fetch();
    }
}
