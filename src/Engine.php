<?php
/*
 * PSX is a open source PHP framework to develop RESTful APIs.
 * For the current version and informations visit <http://phpsx.org>
 *
 * Copyright 2010-2020 Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace PSX\Engine\Roadrunner;

use PSX\Engine\DispatchInterface;
use PSX\Engine\EngineInterface;
use PSX\Http\Request;
use PSX\Http\RequestInterface;
use PSX\Http\ResponseInterface;
use PSX\Http\Server\ResponseFactory;
use PSX\Uri\Uri;
use Spiral\Goridge\StreamRelay;
use Spiral\RoadRunner\HttpClient;
use Spiral\RoadRunner\Worker;

/**
 * Uses the Roadrunner HTTP server
 *
 * @see     https://github.com/spiral/roadrunner
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    http://phpsx.org
 */
class Engine implements EngineInterface
{
    /**
     * @var Worker
     */
    private $worker;

    /**
     * @var HttpClient
     */
    private $httpClient;

    public function __construct()
    {
        $this->worker     = new Worker(new StreamRelay(STDIN, STDOUT));
        $this->httpClient = new HttpClient($this->worker);
    }

    /**
     * @inheritdoc
     */
    public function serve(DispatchInterface $dispatch): void
    {
        while ($request = $this->acceptRequest()) {
            try {
                $response = (new ResponseFactory())->createResponse();
                $response = $dispatch->route($request, $response);

                $this->respond($response);
            } catch (\Throwable $e) {
                $this->worker->error((string) $e);
            }
        }
    }

    /**
     * @return RequestInterface|null
     */
    public function acceptRequest()
    {
        $rawRequest = $this->httpClient->acceptRequest();
        if ($rawRequest === null) {
            return null;
        }

        return new Request(
            new Uri($rawRequest['ctx']['uri']),
            $rawRequest['ctx']['method'],
            $rawRequest['ctx']['headers'],
            $rawRequest['body']
        );
    }

    /**
     * @param ResponseInterface $response
     */
    public function respond(ResponseInterface $response): void
    {
        $this->httpClient->respond(
            $response->getStatusCode(),
            $response->getBody()->__toString(),
            $response->getHeaders()
        );
    }
}
