<?php

/**
 * PHPUnit bootstrap.
 *
 * Unit tests (Domain + Application) never need WordPress, so the WordPress
 * test library is only loaded when it is available. Integration tests skip
 * themselves when WordPress is not loaded.
 *
 * To run the integration suite, install the WordPress test scaffolding first:
 *
 *     bash bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$wpa_tests_dir = getenv('WP_TESTS_DIR');
if (! $wpa_tests_dir) {
    $wpa_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (file_exists($wpa_tests_dir . '/includes/functions.php')) {
    require_once $wpa_tests_dir . '/includes/functions.php';

    tests_add_filter(
        'muplugins_loaded',
        static function (): void {
            require dirname(__DIR__) . '/wp-agents.php';
        }
    );

    require $wpa_tests_dir . '/includes/bootstrap.php';
}
