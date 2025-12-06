<?php

declare(strict_types=1);

namespace Emercury\Smtp\Statistics;

use Emercury\Smtp\Contracts\EmailLoggerInterface;

class AdvancedStatistics
{
    private EmailLoggerInterface $emailLogger;

    public function __construct(EmailLoggerInterface $emailLogger)
    {
        $this->emailLogger = $emailLogger;
    }

    public function getChartData(int $days = 7): array
    {
        $data = $this->emailLogger->getHourlyStatistics($days);

        $chartData = [
            'labels' => [],
            'sent' => [],
            'failed' => [],
        ];

        foreach ($data as $row) {
            $label = date('M j, H:00', strtotime($row['date'] . ' ' . $row['hour'] . ':00:00'));
            $chartData['labels'][] = $label;
            $chartData['sent'][] = (int) $row['sent_count'];
            $chartData['failed'][] = (int) $row['failed_count'];
        }

        return $chartData;
    }

    public function getDailyChartData(int $days = 30): array
    {
        $data = $this->emailLogger->getDailyStatistics($days);

        $chartData = [
            'labels' => [],
            'sent' => [],
            'failed' => [],
            'total' => [],
        ];

        foreach ($data as $row) {
            $chartData['labels'][] = date('M j', strtotime($row['date']));
            $chartData['sent'][] = (int) $row['sent'];
            $chartData['failed'][] = (int) $row['failed'];
            $chartData['total'][] = (int) $row['total'];
        }

        return $chartData;
    }

    public function getKeyMetrics(): array
    {
        $today = $this->emailLogger->getStatistics('today');
        $week = $this->emailLogger->getStatistics('week');
        $month = $this->emailLogger->getStatistics('month');

        return [
            'today' => $today,
            'week' => $week,
            'month' => $month,
            'trends' => $this->calculateTrends(),
        ];
    }

    private function calculateTrends(): array
    {
        //  Сравнение текущей недели с предыдущей
        $currentWeek = $this->emailLogger->getStatistics('week');

        // Здесь можно добавить логику для получения статистики за предыдущую неделю
        // и расчета процентного изменения

        return [
            'sent_change' => 0, // +15% или -5%
            'failed_change' => 0,
            'success_rate_change' => 0,
        ];
    }
}