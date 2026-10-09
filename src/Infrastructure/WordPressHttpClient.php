<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

use AgentStudio\Contracts\Http\HttpClient;
use AgentStudio\Contracts\Http\HttpException;
use AgentStudio\Contracts\Http\HttpRequest;
use AgentStudio\Contracts\Http\HttpResponse;

/**
 * Sends requests through the WordPress HTTP API, so proxies, test mocks and
 * the http_request_host_is_external filter all apply.
 *
 * Uses the "safe" variant: addresses on the local network are refused unless
 * a site allows them, because remote servers can point us at other URLs (for
 * example an OAuth server named in a server's metadata).
 */
final class WordPressHttpClient implements HttpClient
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    public function send(HttpRequest $request): HttpResponse
    {
        $args = [
            'method' => $request->method,
            'headers' => $request->headers,
            'timeout' => (float) $request->timeout,
            'redirection' => 3,
            'limit_response_size' => self::MAX_BYTES,
            'user-agent' => 'AgentStudio/' . AGENT_STUDIO_VERSION . '; ' . home_url('/'),
        ];
        if ($request->body !== '') {
            $args['body'] = $request->body;
        }

        $response = wp_safe_remote_request($request->url, $args);

        if (is_wp_error($response)) {
            throw new HttpException($response->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- not output; shown escaped by the caller.
        }

        $headers = [];
        foreach (wp_remote_retrieve_headers($response) as $name => $value) {
            $headers[strtolower((string) $name)] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return new HttpResponse(
            (int) wp_remote_retrieve_response_code($response),
            $headers,
            (string) wp_remote_retrieve_body($response)
        );
    }
}
