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

class Eos extends Base
{
    /**
     * Test to ensure the response processing is correct
     *
     * @return void
     */
    public function testResponses()
    {
        $result = $this->queryTest(
            '127.0.0.1:27015',
            'eos',
            [],
            false,
            ['skip_http_requests' => true]
        );

        $this->assertFalse($result['gq_online']);
    }
}
