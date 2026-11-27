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
 * The Hero's Legacy official dedicated server (Steam A2S).
 *
 * Gameplay defaults to UDP 7777, with an independently configured Steam query
 * socket at UDP 27016. Supply query_port when that socket is changed.
 * LAN-only visibility did not answer A2S in the tested release.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
class Heroslegacy extends Source
{
    protected string $name = 'heroslegacy';
    protected string $name_long = "The Hero's Legacy";
    protected int $state = self::STATE_BETA;
    protected ?string $join_link = null;

    /** @param array<string, mixed> $options */
    public function __construct(array $options = [])
    {
        // The tested official release did not answer A2S_RULES.
        $options += ['query_rules' => false];
        parent::__construct($options);
    }

    public function findQueryPort(int $clientPort): int
    {
        return 27016;
    }
}
