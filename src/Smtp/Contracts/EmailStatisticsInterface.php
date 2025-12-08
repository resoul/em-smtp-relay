<?php

declare(strict_types=1);

namespace Emercury\Smtp\Contracts;

interface EmailStatisticsInterface
{
    public function getTotalSent(string $period = 'today'): int;
    public function getTotalFailed(string $period = 'today'): int;
    public function getFailureRate(string $period = 'today'): float;

    /**
     * @return array<mixed>
     */
    public function getRecentLogs(int $limit = 10): array;

    /**
     * @return array<mixed>
     */
    public function getSummary(): array;
}
