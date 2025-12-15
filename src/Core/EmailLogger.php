<?php

declare(strict_types=1);

namespace Emercury\Smtp\Core;

use Emercury\Smtp\Contracts\EmailLoggerInterface;

class EmailLogger implements EmailLoggerInterface
{
    private DatabaseManager $db;

    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
    }

    public function logSent(array $emailData): void
    {
        global $wpdb;

        $table = $this->db->getLogsTableName();

        $wpdb->insert($table, [
            'status' => 'sent',
            'recipient' => $this->sanitizeRecipients($emailData['to'] ?? ''),
            'subject' => sanitize_text_field($emailData['subject'] ?? ''),
            'metadata' => wp_json_encode($emailData),
            'created_at' => current_time('mysql'),
        ]);

        $this->updateStatistics('sent');
    }

    public function logFailed(array $emailData, string $error): void
    {
        global $wpdb;

        $table = $this->db->getLogsTableName();

        $wpdb->insert($table, [
            'status' => 'failed',
            'recipient' => $this->sanitizeRecipients($emailData['to'] ?? ''),
            'subject' => sanitize_text_field($emailData['subject'] ?? ''),
            'error_message' => sanitize_text_field($error),
            'metadata' => wp_json_encode($emailData),
            'created_at' => current_time('mysql'),
        ]);

        $this->updateStatistics('failed');
    }

    public function getRecentLogs(int $limit = 50, ?string $status = null): array
    {
        global $wpdb;

        $table = $this->db->getLogsTableName();

        $sql = "SELECT * FROM $table";

        if ($status !== null) {
            $sql .= $wpdb->prepare(" WHERE status = %s", $status);
        }

        $sql .= " ORDER BY created_at DESC LIMIT %d";

        return $wpdb->get_results($wpdb->prepare($sql, $limit), ARRAY_A);
    }

    public function clearOldLogs(int $daysToKeep = 30): int
    {
        global $wpdb;

        $table = $this->db->getLogsTableName();
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < %s",
            $cutoffDate
        ));

        return $deleted ?: 0;
    }

    public function getStatistics(string $period = 'today'): array
    {
        global $wpdb;

        $table = $this->db->getLogsTableName();
        $dateFilter = $this->getDateFilter($period);

        $result = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                COUNT(*) as total
            FROM $table
            WHERE created_at >= %s
        ", $dateFilter), ARRAY_A);

        $sent = (int) ($result['sent'] ?? 0);
        $failed = (int) ($result['failed'] ?? 0);
        $total = $sent + $failed;

        return [
            'sent' => $sent,
            'failed' => $failed,
            'total' => $total,
            'success_rate' => $total > 0 ? round(($sent / $total) * 100, 2) : 0,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
        ];
    }

    public function getHourlyStatistics(int $days = 7): array
    {
        global $wpdb;

        $table = $this->db->getStatsTableName();
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                date,
                hour,
                sent_count,
                failed_count
            FROM $table
            WHERE date >= %s
            ORDER BY date ASC, hour ASC
        ", $startDate), ARRAY_A);

        return $results ?: [];
    }

    public function getDailyStatistics(int $days = 30): array
    {
        global $wpdb;

        $table = $this->db->getStatsTableName();
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                date,
                SUM(sent_count) as sent,
                SUM(failed_count) as failed,
                SUM(sent_count + failed_count) as total
            FROM $table
            WHERE date >= %s
            GROUP BY date
            ORDER BY date ASC
        ", $startDate), ARRAY_A);

        return $results ?: [];
    }

    public function getLogsTableName(): string
    {
        return $this->db->getLogsTableName();
    }

    private function updateStatistics(string $status): void
    {
        global $wpdb;

        $table = $this->db->getStatsTableName();
        $date = current_time('Y-m-d');
        $hour = (int) current_time('H');

        $field = $status === 'sent' ? 'sent_count' : 'failed_count';

        $wpdb->query($wpdb->prepare("
            INSERT INTO $table (date, hour, {$field})
            VALUES (%s, %d, 1)
            ON DUPLICATE KEY UPDATE {$field} = {$field} + 1
        ", $date, $hour));
    }

    private function sanitizeRecipients($recipients): string
    {
        if (is_array($recipients)) {
            $recipients = implode(', ', $recipients);
        }
        return sanitize_text_field($recipients);
    }

    private function getDateFilter(string $period): string
    {
        switch ($period) {
            case 'today':
                return current_time('Y-m-d 00:00:00');
            case 'week':
                return date('Y-m-d 00:00:00', strtotime('-7 days'));
            case 'month':
                return date('Y-m-d 00:00:00', strtotime('-30 days'));
            case 'all':
            default:
                return '1970-01-01 00:00:00';
        }
    }
}