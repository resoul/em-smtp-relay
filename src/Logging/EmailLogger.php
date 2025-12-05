<?php

declare(strict_types=1);

namespace Emercury\Smtp\Logging;

use Emercury\Smtp\Contracts\EmailLoggerInterface;

class EmailLogger implements EmailLoggerInterface
{
    private const OPTION_KEY = 'em_smtp_email_logs';
    private const MAX_LOGS = 500;

    public function logSent(array $emailData): void
    {
        $this->addLog([
            'status' => 'sent',
            'to' => $this->sanitizeRecipients($emailData['to'] ?? ''),
            'subject' => sanitize_text_field($emailData['subject'] ?? ''),
            'timestamp' => current_time('mysql'),
            'date' => current_time('Y-m-d'),
        ]);
    }

    public function logFailed(array $emailData, string $error): void
    {
        $this->addLog([
            'status' => 'failed',
            'to' => $this->sanitizeRecipients($emailData['to'] ?? ''),
            'subject' => sanitize_text_field($emailData['subject'] ?? ''),
            'error' => sanitize_text_field($error),
            'timestamp' => current_time('mysql'),
            'date' => current_time('Y-m-d'),
        ]);
    }

    public function getRecentLogs(int $limit = 50, ?string $status = null): array
    {
        $logs = get_option(self::OPTION_KEY, []);

        if ($status !== null) {
            $logs = array_filter($logs, fn($log) => $log['status'] === $status);
        }

        return array_slice(array_reverse($logs), 0, $limit);
    }

    public function clearOldLogs(int $daysToKeep = 30): int
    {
        $logs = get_option(self::OPTION_KEY, []);
        $cutoffDate = date('Y-m-d', strtotime("-{$daysToKeep} days"));

        $originalCount = count($logs);
        $logs = array_filter($logs, fn($log) => $log['date'] >= $cutoffDate);

        update_option(self::OPTION_KEY, array_values($logs));

        return $originalCount - count($logs);
    }

    public function getStatistics(string $period = 'today'): array
    {
        $logs = get_option(self::OPTION_KEY, []);
        $dateFilter = $this->getDateFilter($period);

        $filteredLogs = array_filter($logs, fn($log) => $log['date'] >= $dateFilter);

        $sent = count(array_filter($filteredLogs, fn($log) => $log['status'] === 'sent'));
        $failed = count(array_filter($filteredLogs, fn($log) => $log['status'] === 'failed'));
        $total = $sent + $failed;

        return [
            'sent' => $sent,
            'failed' => $failed,
            'total' => $total,
            'success_rate' => $total > 0 ? round(($sent / $total) * 100, 2) : 0,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
        ];
    }

    private function addLog(array $logEntry): void
    {
        $logs = get_option(self::OPTION_KEY, []);

        $logs[] = $logEntry;

        // Ограничить количество логов
        if (count($logs) > self::MAX_LOGS) {
            $logs = array_slice($logs, -self::MAX_LOGS);
        }

        update_option(self::OPTION_KEY, $logs);
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
                return current_time('Y-m-d');
            case 'week':
                return date('Y-m-d', strtotime('-7 days'));
            case 'month':
                return date('Y-m-d', strtotime('-30 days'));
            case 'all':
            default:
                return '1970-01-01';
        }
    }
}