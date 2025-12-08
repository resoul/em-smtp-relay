<?php

declare(strict_types=1);

namespace Emercury\Smtp\App;

class DatabaseManager
{
    private const TABLE_LOGS = 'em_smtp_logs';
    private const TABLE_STATS = 'em_smtp_statistics';
    private const DB_VERSION = '1.0';

    public function __construct()
    {
        $this->maybeCreateTables();
    }

    private function maybeCreateTables(): void
    {
        $installedVersion = get_option('em_smtp_db_version', '0');

        if (version_compare($installedVersion, self::DB_VERSION, '<')) {
            $this->createTables();
            update_option('em_smtp_db_version', self::DB_VERSION);
        }
    }

    private function createTables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_logs = $wpdb->prefix . self::TABLE_LOGS;
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            status varchar(20) NOT NULL,
            recipient varchar(255) NOT NULL,
            subject text NOT NULL,
            error_message text NULL,
            metadata longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_at (created_at),
            KEY status_date (status, created_at)
        ) $charset_collate;";

        $table_stats = $wpdb->prefix . self::TABLE_STATS;
        $sql_stats = "CREATE TABLE IF NOT EXISTS $table_stats (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            hour tinyint(2) NOT NULL DEFAULT 0,
            sent_count int(11) NOT NULL DEFAULT 0,
            failed_count int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY date_hour (date, hour)
        ) $charset_collate;";

        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php'); // @codeCoverageIgnore
        }

        dbDelta($sql_logs);
        dbDelta($sql_stats);
    }

    public function getLogsTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_LOGS;
    }

    public function getStatsTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_STATS;
    }

    public function dropTables(): void
    {
        global $wpdb;

        $table_logs = $this->getLogsTableName();
        $table_stats = $this->getStatsTableName();

        $wpdb->query("DROP TABLE IF EXISTS $table_logs");
        $wpdb->query("DROP TABLE IF EXISTS $table_stats");

        delete_option('em_smtp_db_version');
    }
}