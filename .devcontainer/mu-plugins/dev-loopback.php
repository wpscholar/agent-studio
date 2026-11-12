<?php

/**
 * Plugin Name: Dev loopback (devcontainer only)
 * Description: Lets WP-Cron start in the local Docker setup.
 *
 * The site URL is http://localhost:8484, but that port exists only on the host.
 * Inside the containers it reaches nothing, so WordPress's request to start
 * WP-Cron fails and queued agent runs never start. Send that request to the web
 * server's name on the Docker network instead.
 *
 * Mounted by .devcontainer/docker-compose.yml; never part of the plugin.
 */

declare(strict_types=1);

add_filter(
    'cron_request',
    static function (array $request): array {
        $request['url'] = (string) preg_replace('#^https?://[^/]+#', 'http://wordpress', $request['url']);

        return $request;
    }
);
