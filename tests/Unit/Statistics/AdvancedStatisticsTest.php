<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Statistics;

use Emercury\Smtp\Statistics\AdvancedStatistics;
use Emercury\Smtp\Contracts\EmailLoggerInterface;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class AdvancedStatisticsTest extends TestCase
{
    private AdvancedStatistics $statistics;
    private $emailLogger;
    private $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('ARRAY_A')) {
            define('ARRAY_A', 'ARRAY_A');
        }

        $this->emailLogger = Mockery::mock(EmailLoggerInterface::class);
        $this->wpdb = Mockery::mock('wpdb');

        global $wpdb;
        $wpdb = $this->wpdb;

        $this->statistics = new AdvancedStatistics($this->emailLogger);
    }

    public function testGetChartDataReturnsFormattedHourlyData(): void
    {
        $hourlyData = [
            ['date' => '2024-01-15', 'hour' => 10, 'sent_count' => 50, 'failed_count' => 5],
            ['date' => '2024-01-15', 'hour' => 11, 'sent_count' => 45, 'failed_count' => 3],
            ['date' => '2024-01-15', 'hour' => 12, 'sent_count' => 60, 'failed_count' => 2],
        ];

        $this->emailLogger
            ->shouldReceive('getHourlyStatistics')
            ->once()
            ->with(7)
            ->andReturn($hourlyData);

        $result = $this->statistics->getChartData(7);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertCount(3, $result['labels']);
        $this->assertCount(3, $result['sent']);
        $this->assertCount(3, $result['failed']);
    }

    public function testGetChartDataWithDefaultDays(): void
    {
        $this->emailLogger
            ->shouldReceive('getHourlyStatistics')
            ->once()
            ->with(7)
            ->andReturn([]);

        $result = $this->statistics->getChartData();

        $this->assertIsArray($result);
        $this->assertEmpty($result['labels']);
        $this->assertEmpty($result['sent']);
        $this->assertEmpty($result['failed']);
    }

    public function testGetChartDataWithCustomDays(): void
    {
        $this->emailLogger
            ->shouldReceive('getHourlyStatistics')
            ->once()
            ->with(30)
            ->andReturn([]);

        $result = $this->statistics->getChartData(30);

        $this->assertIsArray($result);
    }

    public function testGetChartDataReturnsCorrectCounts(): void
    {
        $hourlyData = [
            ['date' => '2024-01-15', 'hour' => 10, 'sent_count' => 100, 'failed_count' => 10],
            ['date' => '2024-01-15', 'hour' => 11, 'sent_count' => 200, 'failed_count' => 20],
        ];

        $this->emailLogger
            ->shouldReceive('getHourlyStatistics')
            ->once()
            ->andReturn($hourlyData);

        $result = $this->statistics->getChartData(7);

        $this->assertEquals([100, 200], $result['sent']);
        $this->assertEquals([10, 20], $result['failed']);
    }

    public function testGetDailyChartDataReturnsFormattedDailyData(): void
    {
        $dailyData = [
            ['date' => '2024-01-15', 'sent' => 500, 'failed' => 50, 'total' => 550],
            ['date' => '2024-01-16', 'sent' => 600, 'failed' => 40, 'total' => 640],
            ['date' => '2024-01-17', 'sent' => 550, 'failed' => 30, 'total' => 580],
        ];

        $this->emailLogger
            ->shouldReceive('getDailyStatistics')
            ->once()
            ->with(30)
            ->andReturn($dailyData);

        $result = $this->statistics->getDailyChartData(30);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertCount(3, $result['labels']);
        $this->assertCount(3, $result['sent']);
        $this->assertCount(3, $result['failed']);
        $this->assertCount(3, $result['total']);
    }

    public function testGetDailyChartDataWithDefaultDays(): void
    {
        $this->emailLogger
            ->shouldReceive('getDailyStatistics')
            ->once()
            ->with(30)
            ->andReturn([]);

        $result = $this->statistics->getDailyChartData();

        $this->assertIsArray($result);
        $this->assertEmpty($result['labels']);
    }

    public function testGetDailyChartDataReturnsCorrectCounts(): void
    {
        $dailyData = [
            ['date' => '2024-01-15', 'sent' => 1000, 'failed' => 100, 'total' => 1100],
            ['date' => '2024-01-16', 'sent' => 2000, 'failed' => 200, 'total' => 2200],
        ];

        $this->emailLogger
            ->shouldReceive('getDailyStatistics')
            ->once()
            ->andReturn($dailyData);

        $result = $this->statistics->getDailyChartData(30);

        $this->assertEquals([1000, 2000], $result['sent']);
        $this->assertEquals([100, 200], $result['failed']);
        $this->assertEquals([1100, 2200], $result['total']);
    }

    public function testGetKeyMetricsReturnsAllPeriods(): void
    {
        $this->setupKeyMetricsExpectations();

        $result = $this->statistics->getKeyMetrics();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('today', $result);
        $this->assertArrayHasKey('week', $result);
        $this->assertArrayHasKey('month', $result);
        $this->assertArrayHasKey('trends', $result);
    }

    public function testGetKeyMetricsContainsTodayData(): void
    {
        $todayData = [
            'sent' => 50,
            'failed' => 5,
            'total' => 55,
            'success_rate' => 90.91,
            'failure_rate' => 9.09,
        ];

        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->with('today')
            ->andReturn($todayData);

        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->with('week')
            ->andReturn([]);

        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->with('month')
            ->andReturn([]);

        $this->setupWeekStatisticsExpectations();

        $result = $this->statistics->getKeyMetrics();

        $this->assertEquals($todayData, $result['today']);
    }

    public function testGetKeyMetricsContainsWeekData(): void
    {
        $weekData = [
            'sent' => 350,
            'failed' => 25,
            'total' => 375,
            'success_rate' => 93.33,
            'failure_rate' => 6.67,
        ];

        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->with('today')
            ->andReturn([]);

        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->with('week')
            ->andReturn($weekData);

        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->with('month')
            ->andReturn([]);

        $this->setupWeekStatisticsExpectations();

        $result = $this->statistics->getKeyMetrics();

        $this->assertEquals($weekData, $result['week']);
    }

    public function testGetKeyMetricsContainsMonthData(): void
    {
        $monthData = [
            'sent' => 1500,
            'failed' => 100,
            'total' => 1600,
            'success_rate' => 93.75,
            'failure_rate' => 6.25,
        ];

        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->with('today')
            ->andReturn([]);

        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->with('week')
            ->andReturn([]);

        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->with('month')
            ->andReturn($monthData);

        $this->setupWeekStatisticsExpectations();

        $result = $this->statistics->getKeyMetrics();

        $this->assertEquals($monthData, $result['month']);
    }

    public function testGetKeyMetricsCalculatesTrendsWithPositiveChange(): void
    {
        $this->setupKeyMetricsExpectations();

        $this->emailLogger
            ->shouldReceive('getLogsTableName')
            ->times(2)
            ->andReturn('wp_em_smtp_logs');

        $this->wpdb
            ->shouldReceive('prepare')
            ->times(2)
            ->andReturn('SELECT...');

        // Current week stats
        $this->wpdb
            ->shouldReceive('get_row')
            ->once()
            ->andReturn([
                'sent' => '100',
                'failed' => '10',
                'total' => '110'
            ]);

        // Previous week stats
        $this->wpdb
            ->shouldReceive('get_row')
            ->once()
            ->andReturn([
                'sent' => '80',
                'failed' => '12',
                'total' => '92'
            ]);

        $result = $this->statistics->getKeyMetrics();

        $this->assertArrayHasKey('trends', $result);
        $this->assertArrayHasKey('sent_change', $result['trends']);
        $this->assertArrayHasKey('failed_change', $result['trends']);
        $this->assertArrayHasKey('success_rate_change', $result['trends']);
    }

    public function testGetKeyMetricsCalculatesTrendsWithNegativeChange(): void
    {
        $this->setupKeyMetricsExpectations();

        $this->emailLogger
            ->shouldReceive('getLogsTableName')
            ->times(2)
            ->andReturn('wp_em_smtp_logs');

        $this->wpdb
            ->shouldReceive('prepare')
            ->times(2)
            ->andReturn('SELECT...');

        // Current week stats - decreased
        $this->wpdb
            ->shouldReceive('get_row')
            ->once()
            ->andReturn([
                'sent' => '80',
                'failed' => '10',
                'total' => '90'
            ]);

        // Previous week stats - higher
        $this->wpdb
            ->shouldReceive('get_row')
            ->once()
            ->andReturn([
                'sent' => '100',
                'failed' => '5',
                'total' => '105'
            ]);

        $result = $this->statistics->getKeyMetrics();

        $this->assertLessThan(0, $result['trends']['sent_change']);
    }

    public function testCalculatePercentageChangeWithZeroOldValue(): void
    {
        $this->setupKeyMetricsExpectations();

        $this->emailLogger
            ->shouldReceive('getLogsTableName')
            ->times(2)
            ->andReturn('wp_em_smtp_logs');

        $this->wpdb
            ->shouldReceive('prepare')
            ->times(2)
            ->andReturn('SELECT...');

        // Current week stats
        $this->wpdb
            ->shouldReceive('get_row')
            ->once()
            ->andReturn([
                'sent' => '100',
                'failed' => '10',
                'total' => '110'
            ]);

        // Previous week stats - zero
        $this->wpdb
            ->shouldReceive('get_row')
            ->once()
            ->andReturn([
                'sent' => '0',
                'failed' => '0',
                'total' => '0'
            ]);

        $result = $this->statistics->getKeyMetrics();

        $this->assertEquals(100.0, $result['trends']['sent_change']);
    }

    public function testCalculatePercentageChangeWithBothZero(): void
    {
        $this->setupKeyMetricsExpectations();

        $this->emailLogger
            ->shouldReceive('getLogsTableName')
            ->times(2)
            ->andReturn('wp_em_smtp_logs');

        $this->wpdb
            ->shouldReceive('prepare')
            ->times(2)
            ->andReturn('SELECT...');

        // Both weeks zero
        $this->wpdb
            ->shouldReceive('get_row')
            ->twice()
            ->andReturn([
                'sent' => '0',
                'failed' => '0',
                'total' => '0'
            ]);

        $result = $this->statistics->getKeyMetrics();

        $this->assertEquals(0.0, $result['trends']['sent_change']);
    }

    public function testGetChartDataWithEmptyHourlyData(): void
    {
        $this->emailLogger
            ->shouldReceive('getHourlyStatistics')
            ->once()
            ->with(7)
            ->andReturn([]);

        $result = $this->statistics->getChartData(7);

        $this->assertEmpty($result['labels']);
        $this->assertEmpty($result['sent']);
        $this->assertEmpty($result['failed']);
    }

    public function testGetDailyChartDataWithEmptyDailyData(): void
    {
        $this->emailLogger
            ->shouldReceive('getDailyStatistics')
            ->once()
            ->with(30)
            ->andReturn([]);

        $result = $this->statistics->getDailyChartData(30);

        $this->assertEmpty($result['labels']);
        $this->assertEmpty($result['sent']);
        $this->assertEmpty($result['failed']);
        $this->assertEmpty($result['total']);
    }

    public function testGetChartDataWithSingleHourData(): void
    {
        $hourlyData = [
            ['date' => '2024-01-15', 'hour' => 10, 'sent_count' => 50, 'failed_count' => 5],
        ];

        $this->emailLogger
            ->shouldReceive('getHourlyStatistics')
            ->once()
            ->andReturn($hourlyData);

        $result = $this->statistics->getChartData(7);

        $this->assertCount(1, $result['labels']);
        $this->assertCount(1, $result['sent']);
        $this->assertCount(1, $result['failed']);
    }

    public function testGetDailyChartDataWithSingleDayData(): void
    {
        $dailyData = [
            ['date' => '2024-01-15', 'sent' => 500, 'failed' => 50, 'total' => 550],
        ];

        $this->emailLogger
            ->shouldReceive('getDailyStatistics')
            ->once()
            ->andReturn($dailyData);

        $result = $this->statistics->getDailyChartData(30);

        $this->assertCount(1, $result['labels']);
        $this->assertCount(1, $result['sent']);
        $this->assertCount(1, $result['failed']);
        $this->assertCount(1, $result['total']);
    }

    public function testGetChartDataWithLargePeriod(): void
    {
        $this->emailLogger
            ->shouldReceive('getHourlyStatistics')
            ->once()
            ->with(90)
            ->andReturn([]);

        $result = $this->statistics->getChartData(90);

        $this->assertIsArray($result);
    }

    public function testGetDailyChartDataWithLargePeriod(): void
    {
        $this->emailLogger
            ->shouldReceive('getDailyStatistics')
            ->once()
            ->with(365)
            ->andReturn([]);

        $result = $this->statistics->getDailyChartData(365);

        $this->assertIsArray($result);
    }

    public function testTrendsCalculationRoundsToTwoDecimals(): void
    {
        $this->setupKeyMetricsExpectations();

        $this->emailLogger
            ->shouldReceive('getLogsTableName')
            ->times(2)
            ->andReturn('wp_em_smtp_logs');

        $this->wpdb
            ->shouldReceive('prepare')
            ->times(2)
            ->andReturn('SELECT...');

        // Numbers that result in decimals
        $this->wpdb
            ->shouldReceive('get_row')
            ->once()
            ->andReturn([
                'sent' => '103',
                'failed' => '7',
                'total' => '110'
            ]);

        $this->wpdb
            ->shouldReceive('get_row')
            ->once()
            ->andReturn([
                'sent' => '100',
                'failed' => '10',
                'total' => '110'
            ]);

        $result = $this->statistics->getKeyMetrics();

        // Check that values are rounded to 2 decimals
        $this->assertIsFloat($result['trends']['sent_change']);
        $this->assertLessThanOrEqual(2, strlen(substr(strrchr((string)$result['trends']['sent_change'], '.'), 1)));
    }

    private function setupKeyMetricsExpectations(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->with('today')
            ->andReturn([
                'sent' => 50,
                'failed' => 5,
                'total' => 55,
                'success_rate' => 90.91,
                'failure_rate' => 9.09,
            ]);

        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->with('week')
            ->andReturn([
                'sent' => 350,
                'failed' => 25,
                'total' => 375,
                'success_rate' => 93.33,
                'failure_rate' => 6.67,
            ]);

        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->with('month')
            ->andReturn([
                'sent' => 1500,
                'failed' => 100,
                'total' => 1600,
                'success_rate' => 93.75,
                'failure_rate' => 6.25,
            ]);

        $this->setupWeekStatisticsExpectations();
    }

    private function setupWeekStatisticsExpectations(): void
    {
        $this->emailLogger
            ->shouldReceive('getLogsTableName')
            ->andReturn('wp_em_smtp_logs');

        $this->wpdb
            ->shouldReceive('prepare')
            ->andReturn('SELECT...');

        $this->wpdb
            ->shouldReceive('get_row')
            ->andReturn([
                'sent' => '100',
                'failed' => '10',
                'total' => '110'
            ]);
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = null;

        parent::tearDown();
    }
}