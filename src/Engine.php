<?php
/*
 * PSX is an open source PHP framework to develop RESTful APIs.
 * For the current version and information visit <https://phpsx.org>
 *
 * Copyright (c) Christoph Kappestein <christoph.kappestein@gmail.com>
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
use Spiral\RoadRunner\Http\HttpWorker;
use Spiral\RoadRunner\Worker;

/**
 * Uses the Roadrunner HTTP server
 *
 * @see     https://github.com/spiral/roadrunner
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://phpsx.org
 */
class Engine implements EngineInterface
{
    private Worker $worker;
    private HttpWorker $httpWorker;

    public function __construct()
    {
        $this->worker = Worker::create();
        $this->httpWorker = new HttpWorker($this->worker);
    }

    public function serve(DispatchInterface $dispatch): void
    {
        while ($request = $this->waitRequest()) {
            try {
                $response = (new ResponseFactory())->createResponse();
                $response = $dispatch->route($request, $response);

                $this->respond($response);
            } catch (\Throwable $e) {
                $this->httpWorker->getWorker()->error((string) $e);
            }
        }
    }

    public function waitRequest(): ?RequestInterface
    {
        try {
            $httpRequest = $this->httpWorker->waitRequest();
        } catch (\JsonException) {
            return null;
        }

        if ($httpRequest === null) {
            return null;
        }

        $uri = Uri::parse($httpRequest->uri);
        $uri = $uri->withParameters($httpRequest->query);

        return new Request(
            $uri,
            $httpRequest->method,
            $httpRequest->headers,
            $httpRequest->body
        );
    }

    /**
     * @throws \JsonException
     */
    public function respond(ResponseInterface $response): void
    {
        $this->httpWorker->respond(
            $response->getStatusCode(),
            (string) $response->getBody(),
            $response->getHeaders()
        );
    }
}
