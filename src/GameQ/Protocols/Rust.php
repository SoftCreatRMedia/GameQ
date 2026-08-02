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

/**
 * Class Rust
 *
 * @package GameQ\Protocols
 * @author  Austin Bischoff <austin@codebeard.com>
 */
class Rust extends Source
{
    /** @var list<string> */
    private const SERVER_KEYWORDS = [
        'born',
        'carbon',
        'oxide',
        'modded',
        'cp',
        'cs',
        'gm',
        'mp',
        'pt',
        'qp',
        'st',
        'h',
        'v',
    ];

    /** @var list<string> */
    private const SERVER_TAGS = [
        'monthly',
        'biweekly',
        'weekly',
        'vanilla',
        'hardcore',
        'softcore',
        'pve',
        'roleplay',
        'creative',
        'minigame',
        'training',
        'battlefield',
        'broyale',
        'builds',
        'tut',
        'premium',
    ];

    /** @var list<string> */
    private const REGION_TAGS = ['na', 'sa', 'eu', 'wa', 'ea', 'oc', 'af'];

    /**
     * String name of this protocol class
     */
    protected string $name = 'rust';

    /**
     * Longer string name of this protocol class
     */
    protected string $name_long = "Rust";

    /**
     * Overload so we can get max players from mp of keywords and num players from cp keyword
     *
     * @return array<string, mixed>
     * @throws ProtocolException
     */
    protected function processDetails(Buffer $buffer): array
    {
        $results = parent::processDetails($buffer);

        if (!isset($results['keywords']) || !is_string($results['keywords']) || $results['keywords'] === '') {
            return $results;
        }

        $parsed = $this->parseKeywords(explode(',', $results['keywords']));
        $results = array_merge($results, $parsed);
        $serverKeywords = $parsed['server.keywords'];

        if (isset($serverKeywords['mp']) && is_numeric($serverKeywords['mp'])) {
            $results['max_players'] = (int) $serverKeywords['mp'];
        }

        if (isset($serverKeywords['cp']) && is_numeric($serverKeywords['cp'])) {
            $results['num_players'] = (int) $serverKeywords['cp'];
        }

        return $results;
    }

    /**
     * @param list<string> $keywords
     * @return array{
     *     'server.keywords': array<string, string|true>,
     *     'server.tags': list<string>,
     *     'unhandled.tags': list<string>,
     *     region?: string
     * }
     */
    protected function parseKeywords(array $keywords): array
    {
        $result = [
            'server.keywords' => [],
            'server.tags' => [],
            'unhandled.tags' => [],
        ];

        foreach ($keywords as $keyword) {
            $keyword = strtolower(trim($keyword));

            if ($keyword === '') {
                continue;
            }

            if (in_array($keyword, self::SERVER_TAGS, true)) {
                $result['server.tags'][] = $keyword;

                continue;
            }

            if (in_array($keyword, self::REGION_TAGS, true)) {
                $result['region'] = strtoupper($keyword);

                continue;
            }

            $parsed = false;

            foreach (self::SERVER_KEYWORDS as $name) {
                if (!str_starts_with($keyword, $name)) {
                    continue;
                }

                $value = substr($keyword, strlen($name));
                $result['server.keywords'][$name] = $value !== '' ? $value : true;
                $parsed = true;

                break;
            }

            if (!$parsed) {
                $result['unhandled.tags'][] = $keyword;
            }
        }

        return $result;
    }
}
