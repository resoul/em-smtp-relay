<?php

declare(strict_types=1);

namespace Emercury\Smtp\Contracts;

interface EmailLoggerInterface
{
    /**
     * Log successful email
     *
     * @param array $emailData Email data
     * @return void
     */
    public function logSent(array $emailData): void;

    /**
     * Log failed email
     *
     * @param array $emailData Email data
     * @param string $error Error message
     * @return void
     */
    public function logFailed(array $emailData, string $error): void;

    /**
     * Get recent logs
     *
     * @param int $limit Number of logs
     * @param string|null $status Filter by status (sent/failed)
     * @return array
     */
    public function getRecentLogs(int $limit = 50, ?string $status = null): array;

    /**
     * Clear old logs
     *
     * @param int $daysToKeep Days to keep logs
     * @return int Number of deleted logs
     */
    public function clearOldLogs(int $daysToKeep = 30): int;

    /**
     * Get log statistics
     *
     * @param string $period Period (today, week, month, all)
     * @return array
     */
    public function getStatistics(string $period = 'today'): array;
}