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
        $currentWeekStats = $this->getWeekStatistics(0);
        $previousWeekStats = $this->getWeekStatistics(1);

        return [
            'sent_change' => $this->calculatePercentageChange(
                $previousWeekStats['sent'],
                $currentWeekStats['sent']
            ),
            'failed_change' => $this->calculatePercentageChange(
                $previousWeekStats['failed'],
                $currentWeekStats['failed']
            ),
            'success_rate_change' => $this->calculatePercentageChange(
                $previousWeekStats['success_rate'],
                $currentWeekStats['success_rate']
            ),
        ];
    }

    private function getWeekStatistics(int $weeksAgo = 0): array
    {
        global $wpdb;

        $table = $this->emailLogger->getLogsTableName();

        $startDate = date('Y-m-d 00:00:00', strtotime("-" . ($weeksAgo * 7 + 7) . " days"));
        $endDate = date('Y-m-d 23:59:59', strtotime("-" . ($weeksAgo * 7) . " days"));

        $result = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                COUNT(*) as total
            FROM $table
            WHERE created_at >= %s AND created_at <= %s
        ", $startDate, $endDate), ARRAY_A);

        $sent = (int) ($result['sent'] ?? 0);
        $failed = (int) ($result['failed'] ?? 0);
        $total = $sent + $failed;

        return [
            'sent' => $sent,
            'failed' => $failed,
            'total' => $total,
            'success_rate' => $total > 0 ? round(($sent / $total) * 100, 2) : 0,
        ];
    }

    private function calculatePercentageChange(float $oldValue, float $newValue): float
    {
        if ($oldValue == 0) {
            return $newValue > 0 ? 100.0 : 0.0;
        }

        $change = (($newValue - $oldValue) / $oldValue) * 100;
        return round($change, 2);
    }
}