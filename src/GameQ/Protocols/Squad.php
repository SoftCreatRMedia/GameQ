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
 * Class Squad
 *
 * Port reference: http://forums.joinsquad.com/topic/9559-query-ports/
 *
 * @package GameQ\Protocols
 * @author  Austin Bischoff <austin@codebeard.com>
 */
class Squad extends Eos
{
    protected string $protocol = 'squad';

    protected string $grant_type = 'external_auth';

    protected ?string $deployment_id = '5dee4062a90b42cd98fcad618b6636c2';

    protected ?string $user_id = 'xyza7891J7d3GU8ZIwCoC5xdBsdoqVWA';

    protected ?string $user_secret = '4SLVBqAm09q776SIlQRTD6moM/bnGAWhDSqOxJAIS0s';

    /**
     * String name of this protocol class
     */
    protected string $name = 'squad';

    /**
     * Longer string name of this protocol class
     */
    protected string $name_long = "Squad";

    /**
     * Process and select the EOS session matching the requested Squad server.
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        $sessions = $this->getServerSessions();

        foreach ($sessions as $session) {
            $attributes = $this->normalizeStringKeyedArray($session['attributes'] ?? null);
            $boundAddress = $attributes['ADDRESSBOUND_s'] ?? null;
            $gameServerPort = $attributes['GAMESERVER_PORT_l'] ?? null;

            if (
                $boundAddress !== $this->serverIp . ':' . $this->serverPortQuery
                && $boundAddress !== '0.0.0.0:' . $this->serverPortQuery
                && $this->normalizeInteger($gameServerPort, -1) !== $this->serverPortQuery
            ) {
                continue;
            }

            $settings = $this->normalizeStringKeyedArray($session['settings'] ?? null);
            $result = new Result();
            $result->add('hostname', $attributes['SERVERNAME_s'] ?? '');
            $result->add('mapname', $attributes['MAPNAME_s'] ?? '');
            $result->add('password', (bool) ($attributes['PASSWORD_b'] ?? false));
            $result->add('version', $attributes['GAMEVERSION_s'] ?? '');
            $result->add('numplayers', $this->normalizeInteger($session['totalPlayers'] ?? 0));
            $result->add('maxplayers', $this->normalizeInteger($settings['maxPublicPlayers'] ?? 0));

            return $result->fetch();
        }

        throw new ProtocolException('No matching Squad session found for the specified port.');
    }
}
