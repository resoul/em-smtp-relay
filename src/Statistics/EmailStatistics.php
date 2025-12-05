<?php

declare(strict_types=1);

namespace Emercury\Smtp\Statistics;

use Emercury\Smtp\Contracts\EmailStatisticsInterface;
use Emercury\Smtp\Contracts\EmailLoggerInterface;

class EmailStatistics implements EmailStatisticsInterface
{
    private EmailLoggerInterface $emailLogger;

    public function __construct(EmailLoggerInterface $emailLogger)
    {
        $this->emailLogger = $emailLogger;
    }

    public function getTotalSent(string $period = 'today'): int
    {
        $stats = $this->emailLogger->getStatistics($period);
        return $stats['sent'];
    }

    public function getTotalFailed(string $period = 'today'): int
    {
        $stats = $this->emailLogger->getStatistics($period);
        return $stats['failed'];
    }

    public function getFailureRate(string $period = 'today'): float
    {
        $stats = $this->emailLogger->getStatistics($period);
        return $stats['failure_rate'];
    }

    public function getRecentLogs(int $limit = 10): array
    {
        return $this->emailLogger->getRecentLogs($limit);
    }

    public function getSummary(): array
    {
        return [
            'today' => $this->emailLogger->getStatistics('today'),
            'week' => $this->emailLogger->getStatistics('week'),
            'month' => $this->emailLogger->getStatistics('month'),
        ];
    }
}