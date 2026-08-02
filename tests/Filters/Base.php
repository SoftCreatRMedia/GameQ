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

namespace GameQ\Tests\Filters;

use DirectoryIterator;
use GameQ\Tests\TestBase;
use JsonException;
use RuntimeException;

/**
 * Class for testing Filters Base
 *
 * @package GameQ\Tests\Filters
 */
class Base extends TestBase
{
    /**
     * Load up the provider data for the specific filter type
     *
     * @return list<array{
     *     non-empty-string,
     *     non-empty-array<string, array<string, mixed>>,
     *     non-empty-array<string, array<string, mixed>>
     * }>
     */
    public static function loadData(): array
    {
        // Explode the class that called to avoid strict error
        $class = explode('\\', static::class);

        // Determine the folder to grab the provider files and results from
        $providersLookup = sprintf('%s/Providers/%s/', __DIR__, array_pop($class));

        // Init the return array
        $providers = [ ];

        // Grab all of the test files for this filter
        $files = new DirectoryIterator($providersLookup);

        // Iterate over the files in this path
        foreach ($files as $fileinfo) {
            // Skip if we can't read or is dotfile
            if (!$fileinfo->isReadable() || !$fileinfo->isFile()) {
                continue;
            }

            // Split the filename
            [$protocol] = explode('_', $fileinfo->getFilename(), 2);
            $path = $fileinfo->getRealPath();

            if ($protocol === '' || $path === false) {
                throw new RuntimeException('Invalid filter fixture filename.');
            }

            // Get the data
            try {
                $data = json_decode(self::fixtureContents($path), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException("Invalid filter fixture '$path'.", 0, $exception);
            }

            if (!is_array($data)) {
                throw new RuntimeException("Filter fixture '$path' must contain an object.");
            }

            // Add the provider
            $providers[] = [
                $protocol,
                self::normalizeResults($data['raw'] ?? null, $path),
                self::normalizeResults($data['filtered'] ?? null, $path),
            ];
        }

        return $providers;
    }

    /**
     * @return non-empty-array<string, array<string, mixed>>
     */
    private static function normalizeResults(mixed $value, string $path): array
    {
        if (!is_array($value) || $value === []) {
            throw new RuntimeException("Filter fixture '$path' has no results.");
        }

        $results = [];

        foreach ($value as $host => $result) {
            if (!is_string($host) || !is_array($result)) {
                throw new RuntimeException("Filter fixture '$path' contains an invalid server result.");
            }

            $normalizedResult = [];

            foreach ($result as $key => $item) {
                if (!is_string($key)) {
                    throw new RuntimeException("Filter fixture '$path' contains an invalid result key.");
                }

                $normalizedResult[$key] = $item;
            }

            $results[$host] = $normalizedResult;
        }

        return $results;
    }

    /*
     * Real Base tests here
     */

    /**
     * Test options setting on construct
     */
    public function testOptions(): void
    {
        $options = [
            'option1' => 'value1',
            'option2' => 'value2',
        ];

        $mock = new class ($options) extends \GameQ\Filters\Base {
            public function apply(array $result, \GameQ\Server $server): array
            {
                return $result;
            }
        };

        self::assertEquals($options, $mock->getOptions());
    }
}
