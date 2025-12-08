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

        $charsetCollate = $wpdb->get_charset_collate();
        $tableLogs = $wpdb->prefix . self::TABLE_LOGS;
        $sqlLogs = "CREATE TABLE IF NOT EXISTS $tableLogs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            status varchar(20) NOT NULL,
            recipient varchar(255) NOT NULL,
            subject text NOT NULL,
            error_message text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_at (created_at),
            KEY status_date (status, created_at)
        ) $charsetCollate;";

        $tableStats = $wpdb->prefix . self::TABLE_STATS;
        $sqlStats = "CREATE TABLE IF NOT EXISTS $tableStats (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            hour tinyint(2) NOT NULL DEFAULT 0,
            sent_count int(11) NOT NULL DEFAULT 0,
            failed_count int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY date_hour (date, hour)
        ) $charsetCollate;";

        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php'); // @codeCoverageIgnore
        }

        dbDelta($sqlLogs);
        dbDelta($sqlStats);
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

        $tableLogs = $this->getLogsTableName();
        $tableStats = $this->getStatsTableName();

        $wpdb->query("DROP TABLE IF EXISTS $tableLogs");
        $wpdb->query("DROP TABLE IF EXISTS $tableStats");

        delete_option('em_smtp_db_version');
    }
}
