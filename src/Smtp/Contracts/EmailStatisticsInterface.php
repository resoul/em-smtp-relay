<?php

declare(strict_types=1);

namespace Emercury\Smtp\Contracts;

interface EmailStatisticsInterface
{
    /**
     * Get total emails sent in a period
     *
     * @param string $period Period (today, week, month, all)
     * @return int
     */
    public function getTotalSent(string $period = 'today'): int;

    /**
     * Get total failed emails in a period
     *
     * @param string $period Period (today, week, month, all)
     * @return int
     */
    public function getTotalFailed(string $period = 'today'): int;

    /**
     * Get failure rate
     *
     * @param string $period Period (today, week, month, all)
     * @return float Percentage 0-100
     */
    public function getFailureRate(string $period = 'today'): float;

    /**
     * Get recent email logs
     *
     * @param int $limit Number of logs to retrieve
     * @return array
     */
    public function getRecentLogs(int $limit = 10): array;

    /**
     * Get statistics summary
     *
     * @return array
     */
    public function getSummary(): array;
}