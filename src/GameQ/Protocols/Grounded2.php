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

/**
 * Grounded 2 protocol provided by LanternServer.
 *
 * Grounded 2 does not expose a native public status-query interface.
 * LanternServer answers Source A2S queries two UDP ports above the gameplay
 * port.
 *
 * @see https://github.com/humangenome/Lantern
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Grounded2 extends Source
{
    protected string $name = 'grounded2';

    protected string $name_long = 'Grounded 2 (requires LanternServer)';

    protected int $state = self::STATE_BETA;

    protected int $port_diff = 2;

    protected ?string $join_link = null;
}
