<?php

declare(strict_types=1);

namespace Emercury\Smtp\Admin;

use Emercury\Smtp\Contracts\EmailLoggerInterface;
use Emercury\Smtp\Contracts\EmailStatisticsInterface;

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

    /**
     * @return array<mixed>
     */
    public function getRecentLogs(int $limit = 10): array
    {
        return $this->emailLogger->getRecentLogs($limit);
    }

    /**
     * @return array<mixed>
     */
    public function getSummary(): array
    {
        return [
            'today' => $this->emailLogger->getStatistics(),
            'week' => $this->emailLogger->getStatistics('week'),
            'month' => $this->emailLogger->getStatistics('month'),
        ];
    }
}
