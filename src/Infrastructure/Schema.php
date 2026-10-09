<?php

declare(strict_types=1);

namespace AgentStudio\Infrastructure;

/**
 * The plugin's tables: the run log, and conversations held through channels.
 */
final class Schema
{
    public const VERSION = '2';
    public const OPTION = 'agent_studio_db_version';

    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'agent_studio_runs';
    }

    public static function conversations(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'agent_studio_conversations';
    }

    public static function messages(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'agent_studio_messages';
    }

    public static function contacts(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'agent_studio_contacts';
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

        $conversations = self::conversations();
        dbDelta(
            "CREATE TABLE {$conversations} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  agent_id bigint(20) unsigned NOT NULL DEFAULT 0,
  channel_id bigint(20) unsigned NOT NULL DEFAULT 0,
  thread_key varchar(191) NOT NULL DEFAULT '',
  address varchar(255) NOT NULL DEFAULT '',
  summary text NOT NULL,
  summarized_through bigint(20) unsigned NOT NULL DEFAULT 0,
  closed tinyint(1) NOT NULL DEFAULT 0,
  started_at bigint(20) unsigned NOT NULL DEFAULT 0,
  last_activity_at bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY channel_thread (channel_id,thread_key,closed)
) {$charset};"
        );

        $messages = self::messages();
        dbDelta(
            "CREATE TABLE {$messages} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  conversation_id bigint(20) unsigned NOT NULL DEFAULT 0,
  sender varchar(16) NOT NULL DEFAULT '',
  sender_name varchar(191) NOT NULL DEFAULT '',
  text longtext NOT NULL,
  contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
  run_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY conversation (conversation_id,id)
) {$charset};"
        );

        $contacts = self::contacts();
        dbDelta(
            "CREATE TABLE {$contacts} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  channel_id bigint(20) unsigned NOT NULL DEFAULT 0,
  external_id varchar(191) NOT NULL DEFAULT '',
  name varchar(191) NOT NULL DEFAULT '',
  status varchar(16) NOT NULL DEFAULT 'pending',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  address varchar(255) NOT NULL DEFAULT '',
  created_at bigint(20) unsigned NOT NULL DEFAULT 0,
  last_seen_at bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY channel_person (channel_id,external_id)
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
