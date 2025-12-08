<?php

declare(strict_types=1);

namespace Emercury\Smtp\Contracts;

interface EmailLoggerInterface
{
    public function logSent(string $to, string $subject): void;

    public function logFailed(string $to, string $subject, string $error): void;

    /**
     * @return array<mixed>
     */
    public function getRecentLogs(int $limit = 50, ?string $status = null): array;

    /**
     * @return array<mixed>
     */
    public function getDailyStatistics(int $days = 30): array;

    public function clearOldLogs(int $daysToKeep = 30): int;

    /**
     * @return array<mixed>
     */
    public function getHourlyStatistics(int $days = 7): array;

    /**
     * @return array<mixed>
     */
    public function getStatistics(string $period = 'today'): array;
}
