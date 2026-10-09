<?php

/**
 * Stubs for the WordPress Secrets API proposed for 7.2 (wordpress-develop PR #13759),
 * so static analysis knows its shape before it ships.
 */

/**
 * @return true|WP_Error
 */
function wp_set_secret(string $name, string $value) {}

/**
 * @return object|null|WP_Error An object with reveal(): string|WP_Error.
 */
function wp_get_secret(string $name, string $version = 'current') {}

/**
 * @return true|WP_Error
 */
function wp_delete_secret(string $name) {}

/**
 * @return array<int, array<string, mixed>>|WP_Error
 */
function wp_list_secrets(string $namespace = '') {}
