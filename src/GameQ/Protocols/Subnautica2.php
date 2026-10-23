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
 * Source A2S queries provided by the independent BeaconServer runtime.
 *
 * Values describe the responder's advertised state, not verified game policy.
 * Beacon 0.3.135 advertises a fixed map value and zero player scores.
 *
 * @see https://github.com/HumanGenome/BeaconServer
 */
class Subnautica2 extends Source
{
    protected string $name = 'subnautica2';
    protected string $name_long = 'Subnautica 2 (BeaconServer)';
    protected int $port_diff = 2;
    protected int $state = self::STATE_BETA;
    protected ?string $join_link = null;
}
