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

/**
 * Official Nuclear Option dedicated server, using Steam A2S queries.
 *
 * Gameplay defaults to UDP 7777 and queries to UDP 7778. Both ports are
 * independently configurable: supply query_port when the query port is not
 * the configured gameplay port plus one. Hidden servers do not answer A2S
 * in the tested official build. No direct-join URI has been verified.
 *
 * @see https://github.com/Shockfront-Studios/Nuclear-Option-Server-Tools
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Nuclearoption extends Source
{
    protected string $name = 'nuclearoption';
    protected string $name_long = 'Nuclear Option';
    protected int $port_diff = 1;
    protected int $state = self::STATE_BETA;
    protected ?string $join_link = null;
}
