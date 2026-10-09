<?php

declare(strict_types=1);

namespace AgentStudio\Contracts\Http;

interface HttpClient
{
    /**
     * Sends the request. Any HTTP status comes back as a response; only
     * failures to get an answer at all throw.
     *
     * @throws HttpException
     */
    public function send(HttpRequest $request): HttpResponse;
}
