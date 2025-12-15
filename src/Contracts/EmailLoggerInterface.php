<?php

declare(strict_types=1);

namespace Emercury\Smtp\Contracts;

interface EmailLoggerInterface
{
    public function logSent(array $emailData): void;

    public function logFailed(array $emailData, string $error): void;

    public function getRecentLogs(int $limit = 50, ?string $status = null): array;

    public function getDailyStatistics(int $days = 30): array;

    public function clearOldLogs(int $daysToKeep = 30): int;

    public function getHourlyStatistics(int $days = 7): array;

    public function getStatistics(string $period = 'today'): array;
}