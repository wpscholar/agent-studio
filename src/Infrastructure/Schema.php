<?php

declare(strict_types=1);

namespace WPAgents\Infrastructure;

/**
 * The run log table.
 */
final class Schema
{
    public const VERSION = '1';
    public const OPTION = 'wpa_db_version';

    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'wpa_runs';
    }

    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table();
        $charset = $wpdb->get_charset_collate();

        dbDelta(
            "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  agent_id bigint(20) unsigned NOT NULL DEFAULT 0,
  trigger_type varchar(32) NOT NULL DEFAULT '',
  status varchar(24) NOT NULL DEFAULT 'queued',
  dedupe_key varchar(191) NOT NULL DEFAULT '',
  input longtext NOT NULL,
  transcript longtext NOT NULL,
  steps longtext NOT NULL,
  pending longtext NULL,
  output longtext NOT NULL,
  error text NOT NULL,
  tokens_in int(10) unsigned NOT NULL DEFAULT 0,
  tokens_out int(10) unsigned NOT NULL DEFAULT 0,
  created_at bigint(20) unsigned NOT NULL DEFAULT 0,
  started_at bigint(20) unsigned NOT NULL DEFAULT 0,
  finished_at bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY agent_created (agent_id,created_at),
  KEY status_created (status,created_at),
  KEY agent_dedupe (agent_id,dedupe_key)
) {$charset};"
        );

        update_option(self::OPTION, self::VERSION);
    }

    public static function upgradeIfNeeded(): void
    {
        if (get_option(self::OPTION) !== self::VERSION) {
            self::install();
        }
    }
}
