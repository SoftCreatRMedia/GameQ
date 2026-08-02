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

namespace GameQ\Tests\Protocols;

use GameQ\Tests\TestBase;
use GlobIterator;
use JsonException;
use RuntimeException;
use SplFileInfo;

/**
 * Class Base for protocol tests
 *
 * @package GameQ\Tests\Protocols
 */
abstract class Base extends TestBase
{
    /**
     * Shared provider to give protocols the data to test with
     *
     * @return list<array{list<string>, non-empty-array<string, array<string, mixed>>}>
     */
    public static function loadData(): array
    {
        // Explode the class that called to avoid strict error
        $class = explode('\\', static::class);

        // Determine the folder to grab the provider files and results from
        $providersLookup = sprintf('%s/Providers/%s/', __DIR__, array_pop($class));

        // Init the return array
        $providers = [];

        // Do a glob lookup just for the responses
        $files = new GlobIterator($providersLookup . '*_response.txt');

        // Iterate over the list of response files that exists
        foreach ($files as $fileinfo) {
            if (!$fileinfo instanceof SplFileInfo) {
                continue;
            }

            if (!$fileinfo->isReadable() || !$fileinfo->isFile()) {
                continue;
            }

            [$index] = explode('_', $fileinfo->getFilename(), 2);
            $responsePath = $fileinfo->getRealPath();
            $resultPath = sprintf('%s%s_result.json', $providersLookup, $index);

            if ($responsePath === false) {
                throw new RuntimeException('Unable to resolve a protocol response fixture path.');
            }

            $responseContents = file_get_contents($responsePath);
            $resultContents = file_get_contents($resultPath);

            if ($responseContents === false || $resultContents === false) {
                throw new RuntimeException("Unable to read protocol fixtures for '$index'.");
            }

            try {
                $decodedResult = json_decode($resultContents, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException("Invalid result fixture for '$index'.", 0, $exception);
            }

            if (!is_array($decodedResult) || $decodedResult === []) {
                throw new RuntimeException("Result fixture for '$index' must contain a server result.");
            }

            $result = [];

            foreach ($decodedResult as $server => $serverResult) {
                if (!is_string($server) || !is_array($serverResult)) {
                    throw new RuntimeException("Invalid server result in fixture '$index'.");
                }

                $normalizedServerResult = [];

                foreach ($serverResult as $key => $value) {
                    if (!is_string($key)) {
                        throw new RuntimeException("Invalid result key in fixture '$index'.");
                    }

                    $normalizedServerResult[$key] = $value;
                }

                $result[$server] = $normalizedServerResult;
            }

            // Append this data to the providers return
            $providers[] = [
                explode(PHP_EOL . '||' . PHP_EOL, $responseContents),
                $result,
            ];
        }

        return $providers;
    }
}
