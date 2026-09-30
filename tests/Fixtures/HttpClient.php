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

namespace GameQ\Tests\Fixtures;

use GameQ\Http\ClientInterface;
use GameQ\Http\HttpException;
use GameQ\Http\Request;
use GameQ\Http\Response;

/**
 * Captures HTTP requests and supplies queued responses without network access.
 *
 * @author Sascha Greuel <sascha@softcreatr.de>
 */
final class HttpClient implements ClientInterface
{
    /** @var list<Request> */
    public array $requests = [];

    /** @param list<Response|HttpException> $responses */
    public function __construct(private array $responses)
    {
    }

    public function send(Request $request): Response
    {
        $this->requests[] = $request;
        $response = array_shift($this->responses);

        if ($response instanceof HttpException) {
            throw $response;
        }

        if ($response === null) {
            throw new HttpException('Unexpected request in test.');
        }

        return $response;
    }
}
