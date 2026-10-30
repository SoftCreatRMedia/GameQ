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

/**
 * Class for testing Secondstohuman filter
 *
 * @package GameQ\Tests\Filters
 */
class Secondstohuman extends Base
{
    /**
     * @return array<string, array{int|float|string, string}>
     */
    public static function unsafeTimeValues(): array
    {
        $maximum = sprintf(
            '%02d:%02d:%02d',
            intdiv(PHP_INT_MAX, 3600),
            intdiv(PHP_INT_MAX, 60) % 60,
            PHP_INT_MAX % 60,
        );

        return [
            'nan' => [NAN, '00:00:00'],
            'positive infinity' => [INF, $maximum],
            'negative infinity' => [-INF, '00:00:00'],
            'integer maximum' => [PHP_INT_MAX, $maximum],
            'rounded float boundary' => [(float) PHP_INT_MAX, $maximum],
            'large float' => [1.0e30, $maximum],
            'large numeric string' => ['1e100', $maximum],
            'integer maximum string' => [(string) PHP_INT_MAX, $maximum],
            'negative integer' => [-1, '00:00:00'],
            'negative float' => [-123.5, '00:00:00'],
            'negative numeric string' => ['-1e100', '00:00:00'],
            'zero' => [0, '00:00:00'],
            'fractional seconds' => [59.99, '00:00:59'],
            'numeric string' => ['3661.75', '01:01:01'],
            'more than one day' => [90061.25, '25:01:01'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeTimeValues')]
    public function testUnsafeTimesAreFormattedWithoutChangingRawValues(int|float|string $time, string $expected): void
    {
        $server = new \GameQ\Server(['type' => 'source', 'host' => '127.0.0.1:27015']);
        $filter = new \GameQ\Filters\Secondstohuman();
        $result = $filter->apply(['players' => [['time' => $time]]], $server);
        self::assertIsArray($result['players']);
        self::assertIsArray($result['players'][0]);
        $player = $result['players'][0];

        self::assertSame($expected, $player['gq_time_human']);

        if (is_float($time) && is_nan($time)) {
            self::assertIsFloat($player['time']);
            self::assertNan($player['time']);
        } else {
            self::assertSame($time, $player['time']);
        }
    }

    /**
     * Test default filter settings
     */
    public function testFilteredDefault(): void
    {
        $data = [
            'time'    => 8634.0791015625, //02:23:54
            'players' => [
                [
                    'name' => 'fake_player1',
                    'time' => 7650.4604492188, //02:07:30
                ],
                [
                    'name'      => 'fake_player2',
                    'time_conn' => 1333.1260986328, //00:22:13
                ],
            ],
        ];

        $dataAfter = [
            'time'          => 8634.0791015625, //02:23:54
            'gq_time_human' => '02:23:54',
            'players'       => [
                [
                    'name'          => 'fake_player1',
                    'time'          => 7650.4604492188, //02:07:30
                    'gq_time_human' => '02:07:30',
                ],
                [
                    'name'      => 'fake_player2',
                    'time_conn' => 1333.1260986328, //00:22:13
                ],
            ],
        ];

        $server = new \GameQ\Server([
            \GameQ\Server::SERVER_HOST => '127.0.0.1:27015',
            \GameQ\Server::SERVER_TYPE => 'css',
        ]);
        $filter = new \GameQ\Filters\Secondstohuman();

        self::assertEquals($dataAfter, $filter->apply($data, $server));
    }

    /**
     * Test the filter for Seconds to human using custom keys
     */
    public function testFilteredCustom(): void
    {
        $data = [
            'time'    => 8634.0791015625, //02:23:54
            'players' => [
                [
                    'name' => 'fake_player1',
                    'time' => 7650.4604492188, //02:07:30
                ],
                [
                    'name'      => 'fake_player2',
                    'time_conn' => 1333.1260986328, //00:22:13
                ],
            ],
        ];

        $dataAfter = [
            'time'          => 8634.0791015625, //02:23:54
            'gq_time_human' => '02:23:54',
            'players'       => [
                [
                    'name'          => 'fake_player1',
                    'time'          => 7650.4604492188, //02:07:30
                    'gq_time_human' => '02:07:30',
                ],
                [
                    'name'               => 'fake_player2',
                    'time_conn'          => 1333.1260986328, //00:22:13
                    'gq_time_conn_human' => '00:22:13',
                ],
            ],
        ];

        $server = new \GameQ\Server([
            \GameQ\Server::SERVER_HOST => '127.0.0.1:27015',
            \GameQ\Server::SERVER_TYPE => 'css',
        ]);
        $filter = new \GameQ\Filters\Secondstohuman([
            \GameQ\Filters\Secondstohuman::OPTION_TIMEKEYS => ['time', 'time_conn'],
        ]);

        self::assertEquals($dataAfter, $filter->apply($data, $server));
    }
}
