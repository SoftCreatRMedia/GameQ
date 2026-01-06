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
 * ARK: Survival Ascended Protocol Class
 *
 * Extends the EOS protocol and adds ARK-specific server response processing.
 *
 * @package GameQ\Protocols
 * @author  H.Rouatbi
 */
class Arksa extends Eos
{
    /**
     * The protocol being used
     */
    protected string $protocol = 'arksa';

    /**
     * Longer string name of this protocol class
     */
    protected string $name_long = 'ARK: Survival Ascended';

    /**
     * String name of this protocol class
     */
    protected string $name = 'arksa';

    /**
     * Grant type used for authentication
     */
    protected string $grant_type = 'client_credentials';

    /**
     * Deployment ID for the game or application
     */
    protected ?string $deployment_id = 'ad9a8feffb3b4b2ca315546f038c3ae2';

    /**
     * User ID for authentication
     */
    protected ?string $user_id = 'xyza7891muomRmynIIHaJB9COBKkwj6n';

    /**
     * User secret key for authentication
     */
    protected ?string $user_secret = 'PP5UGxysEieNfSrEicaD1N2Bb3TdXuD7xHYcsdUHZ7s';

    /**
     * Process the response from the EOS API and filter ARK-specific server data
     *
     * @throws ProtocolException
     */
    public function processResponse(): array
    {
        $serverData = parent::processResponse();

        // Filter by port to match server sessions
        $filtered = array_filter($serverData, function ($session) {
            return $session['attributes']['ADDRESSBOUND_s'] === "{$this->serverIp}:{$this->serverPortQuery}" ||
                   $session['attributes']['ADDRESSBOUND_s'] === "0.0.0.0:{$this->serverPortQuery}";
        });

        if (!$filtered) {
            throw new ProtocolException('No matching sessions found for the specified port.');
        }

        $session = reset($filtered);

        $result = new Result();

        // Add server items to the result object
        $result->add('hostname', $this->getAttribute($session['attributes'], 'CUSTOMSERVERNAME_s', 'Unknown'));
        $result->add('mapname', $this->getAttribute($session['attributes'], 'MAPNAME_s', 'Unknown'));
        $result->add('password', $this->getAttribute($session['attributes'], 'SERVERPASSWORD_b', false));
        $result->add('numplayers', $this->getAttribute($session, 'totalPlayers', 0));
        $result->add('maxplayers', $this->getAttribute($session['settings'], 'maxPublicPlayers', 0));
        $result->add('anticheat', $this->getAttribute($session['attributes'], 'SERVERUSESBATTLEYE_b', false));
        $result->add('allowJoinInProgress', $this->getAttribute($session['settings'], 'allowJoinInProgress', false));
        $result->add('day', $this->getAttribute($session['attributes'], 'DAYTIME_s', ''));
        $result->add(
            'version',
            "v" . $this->getAttribute($session['attributes'], 'BUILDID_s', '0') . "." .
            $this->getAttribute($session['attributes'], 'MINORBUILDID_s', '0')
        );
        $result->add('pve', (bool) $this->getAttribute($session['attributes'], 'SESSIONISPVE_l', false));
        $result->add('officialserver', (bool) $this->getAttribute($session['attributes'], 'OFFICIALSERVER_s', false));

        // Return the final result
        return $result->fetch();
    }
}
