<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Statistics;

use Emercury\Smtp\Statistics\EmailStatistics;
use Emercury\Smtp\Contracts\EmailLoggerInterface;
use Emercury\Smtp\Tests\TestCase;
use Mockery;

class EmailStatisticsTest extends TestCase
{
    private EmailStatistics $statistics;
    private $emailLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->emailLogger = Mockery::mock(EmailLoggerInterface::class);
        $this->statistics = new EmailStatistics($this->emailLogger);
    }

    public function testGetTotalSentForToday(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('today')
            ->andReturn([
                'sent' => 50,
                'failed' => 5,
                'total' => 55,
                'success_rate' => 90.91,
                'failure_rate' => 9.09,
            ]);

        $result = $this->statistics->getTotalSent('today');

        $this->assertEquals(50, $result);
    }

    public function testGetTotalSentForWeek(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('week')
            ->andReturn([
                'sent' => 350,
                'failed' => 25,
                'total' => 375,
                'success_rate' => 93.33,
                'failure_rate' => 6.67,
            ]);

        $result = $this->statistics->getTotalSent('week');

        $this->assertEquals(350, $result);
    }

    public function testGetTotalSentForMonth(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('month')
            ->andReturn([
                'sent' => 1500,
                'failed' => 100,
                'total' => 1600,
                'success_rate' => 93.75,
                'failure_rate' => 6.25,
            ]);

        $result = $this->statistics->getTotalSent('month');

        $this->assertEquals(1500, $result);
    }

    public function testGetTotalSentForAll(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('all')
            ->andReturn([
                'sent' => 10000,
                'failed' => 500,
                'total' => 10500,
                'success_rate' => 95.24,
                'failure_rate' => 4.76,
            ]);

        $result = $this->statistics->getTotalSent('all');

        $this->assertEquals(10000, $result);
    }

    public function testGetTotalSentDefaultsToToday(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('today')
            ->andReturn([
                'sent' => 25,
                'failed' => 3,
                'total' => 28,
                'success_rate' => 89.29,
                'failure_rate' => 10.71,
            ]);

        $result = $this->statistics->getTotalSent();

        $this->assertEquals(25, $result);
    }

    public function testGetTotalFailedForToday(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('today')
            ->andReturn([
                'sent' => 50,
                'failed' => 5,
                'total' => 55,
                'success_rate' => 90.91,
                'failure_rate' => 9.09,
            ]);

        $result = $this->statistics->getTotalFailed('today');

        $this->assertEquals(5, $result);
    }

    public function testGetTotalFailedForWeek(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('week')
            ->andReturn([
                'sent' => 350,
                'failed' => 25,
                'total' => 375,
                'success_rate' => 93.33,
                'failure_rate' => 6.67,
            ]);

        $result = $this->statistics->getTotalFailed('week');

        $this->assertEquals(25, $result);
    }

    public function testGetTotalFailedForMonth(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('month')
            ->andReturn([
                'sent' => 1500,
                'failed' => 100,
                'total' => 1600,
                'success_rate' => 93.75,
                'failure_rate' => 6.25,
            ]);

        $result = $this->statistics->getTotalFailed('month');

        $this->assertEquals(100, $result);
    }

    public function testGetTotalFailedDefaultsToToday(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('today')
            ->andReturn([
                'sent' => 25,
                'failed' => 3,
                'total' => 28,
                'success_rate' => 89.29,
                'failure_rate' => 10.71,
            ]);

        $result = $this->statistics->getTotalFailed();

        $this->assertEquals(3, $result);
    }

    public function testGetFailureRateForToday(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('today')
            ->andReturn([
                'sent' => 50,
                'failed' => 5,
                'total' => 55,
                'success_rate' => 90.91,
                'failure_rate' => 9.09,
            ]);

        $result = $this->statistics->getFailureRate('today');

        $this->assertEquals(9.09, $result);
    }

    public function testGetFailureRateForWeek(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('week')
            ->andReturn([
                'sent' => 350,
                'failed' => 25,
                'total' => 375,
                'success_rate' => 93.33,
                'failure_rate' => 6.67,
            ]);

        $result = $this->statistics->getFailureRate('week');

        $this->assertEquals(6.67, $result);
    }

    public function testGetFailureRateForMonth(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('month')
            ->andReturn([
                'sent' => 1500,
                'failed' => 100,
                'total' => 1600,
                'success_rate' => 93.75,
                'failure_rate' => 6.25,
            ]);

        $result = $this->statistics->getFailureRate('month');

        $this->assertEquals(6.25, $result);
    }

    public function testGetFailureRateDefaultsToToday(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('today')
            ->andReturn([
                'sent' => 25,
                'failed' => 3,
                'total' => 28,
                'success_rate' => 89.29,
                'failure_rate' => 10.71,
            ]);

        $result = $this->statistics->getFailureRate();

        $this->assertEquals(10.71, $result);
    }

    public function testGetFailureRateWithZeroEmails(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('today')
            ->andReturn([
                'sent' => 0,
                'failed' => 0,
                'total' => 0,
                'success_rate' => 0,
                'failure_rate' => 0,
            ]);

        $result = $this->statistics->getFailureRate('today');

        $this->assertEquals(0, $result);
    }

    public function testGetRecentLogsWithDefaultLimit(): void
    {
        $expectedLogs = [
            ['id' => 1, 'status' => 'sent', 'subject' => 'Test 1'],
            ['id' => 2, 'status' => 'sent', 'subject' => 'Test 2'],
            ['id' => 3, 'status' => 'failed', 'subject' => 'Test 3'],
        ];

        $this->emailLogger
            ->shouldReceive('getRecentLogs')
            ->once()
            ->with(10)
            ->andReturn($expectedLogs);

        $result = $this->statistics->getRecentLogs();

        $this->assertEquals($expectedLogs, $result);
    }

    public function testGetRecentLogsWithCustomLimit(): void
    {
        $expectedLogs = [
            ['id' => 1, 'status' => 'sent', 'subject' => 'Test 1'],
            ['id' => 2, 'status' => 'sent', 'subject' => 'Test 2'],
        ];

        $this->emailLogger
            ->shouldReceive('getRecentLogs')
            ->once()
            ->with(2)
            ->andReturn($expectedLogs);

        $result = $this->statistics->getRecentLogs(2);

        $this->assertEquals($expectedLogs, $result);
    }

    public function testGetRecentLogsWithLargeLimit(): void
    {
        $expectedLogs = array_fill(0, 100, [
            'id' => 1,
            'status' => 'sent',
            'subject' => 'Test'
        ]);

        $this->emailLogger
            ->shouldReceive('getRecentLogs')
            ->once()
            ->with(100)
            ->andReturn($expectedLogs);

        $result = $this->statistics->getRecentLogs(100);

        $this->assertCount(100, $result);
    }

    public function testGetRecentLogsReturnsEmptyArray(): void
    {
        $this->emailLogger
            ->shouldReceive('getRecentLogs')
            ->once()
            ->with(10)
            ->andReturn([]);

        $result = $this->statistics->getRecentLogs();

        $this->assertEmpty($result);
    }

    public function testGetSummaryReturnsAllPeriods(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
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
            ->once()
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
            ->once()
            ->with('month')
            ->andReturn([
                'sent' => 1500,
                'failed' => 100,
                'total' => 1600,
                'success_rate' => 93.75,
                'failure_rate' => 6.25,
            ]);

        $result = $this->statistics->getSummary();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('today', $result);
        $this->assertArrayHasKey('week', $result);
        $this->assertArrayHasKey('month', $result);
    }

    public function testGetSummaryContainsCorrectTodayData(): void
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

        $result = $this->statistics->getSummary();

        $this->assertEquals($todayData, $result['today']);
    }

    public function testGetSummaryContainsCorrectWeekData(): void
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

        $result = $this->statistics->getSummary();

        $this->assertEquals($weekData, $result['week']);
    }

    public function testGetSummaryContainsCorrectMonthData(): void
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

        $result = $this->statistics->getSummary();

        $this->assertEquals($monthData, $result['month']);
    }

    public function testGetSummaryWithAllZeroValues(): void
    {
        $emptyStats = [
            'sent' => 0,
            'failed' => 0,
            'total' => 0,
            'success_rate' => 0,
            'failure_rate' => 0,
        ];

        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->times(3)
            ->andReturn($emptyStats);

        $result = $this->statistics->getSummary();

        $this->assertEquals($emptyStats, $result['today']);
        $this->assertEquals($emptyStats, $result['week']);
        $this->assertEquals($emptyStats, $result['month']);
    }

    public function testGetTotalSentWithZeroValue(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('today')
            ->andReturn([
                'sent' => 0,
                'failed' => 0,
                'total' => 0,
                'success_rate' => 0,
                'failure_rate' => 0,
            ]);

        $result = $this->statistics->getTotalSent('today');

        $this->assertEquals(0, $result);
    }

    public function testGetTotalFailedWithZeroValue(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('today')
            ->andReturn([
                'sent' => 0,
                'failed' => 0,
                'total' => 0,
                'success_rate' => 0,
                'failure_rate' => 0,
            ]);

        $result = $this->statistics->getTotalFailed('today');

        $this->assertEquals(0, $result);
    }

    public function testGetTotalSentWithHighVolume(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('all')
            ->andReturn([
                'sent' => 1000000,
                'failed' => 50000,
                'total' => 1050000,
                'success_rate' => 95.24,
                'failure_rate' => 4.76,
            ]);

        $result = $this->statistics->getTotalSent('all');

        $this->assertEquals(1000000, $result);
    }

    public function testGetFailureRateWith100PercentFailure(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('today')
            ->andReturn([
                'sent' => 0,
                'failed' => 100,
                'total' => 100,
                'success_rate' => 0,
                'failure_rate' => 100,
            ]);

        $result = $this->statistics->getFailureRate('today');

        $this->assertEquals(100, $result);
    }

    public function testGetFailureRateWithPerfectSuccess(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->once()
            ->with('today')
            ->andReturn([
                'sent' => 100,
                'failed' => 0,
                'total' => 100,
                'success_rate' => 100,
                'failure_rate' => 0,
            ]);

        $result = $this->statistics->getFailureRate('today');

        $this->assertEquals(0, $result);
    }

    public function testMultipleCallsToGetTotalSent(): void
    {
        $this->emailLogger
            ->shouldReceive('getStatistics')
            ->times(3)
            ->with('today')
            ->andReturn([
                'sent' => 50,
                'failed' => 5,
                'total' => 55,
                'success_rate' => 90.91,
                'failure_rate' => 9.09,
            ]);

        $result1 = $this->statistics->getTotalSent('today');
        $result2 = $this->statistics->getTotalSent('today');
        $result3 = $this->statistics->getTotalSent('today');

        $this->assertEquals(50, $result1);
        $this->assertEquals(50, $result2);
        $this->assertEquals(50, $result3);
    }

    public function testGetRecentLogsWithMixedStatuses(): void
    {
        $expectedLogs = [
            ['id' => 1, 'status' => 'sent', 'subject' => 'Test 1'],
            ['id' => 2, 'status' => 'failed', 'subject' => 'Test 2'],
            ['id' => 3, 'status' => 'sent', 'subject' => 'Test 3'],
            ['id' => 4, 'status' => 'failed', 'subject' => 'Test 4'],
            ['id' => 5, 'status' => 'sent', 'subject' => 'Test 5'],
        ];

        $this->emailLogger
            ->shouldReceive('getRecentLogs')
            ->once()
            ->with(5)
            ->andReturn($expectedLogs);

        $result = $this->statistics->getRecentLogs(5);

        $this->assertCount(5, $result);

        $sentCount = count(array_filter($result, fn($log) => $log['status'] === 'sent'));
        $failedCount = count(array_filter($result, fn($log) => $log['status'] === 'failed'));

        $this->assertEquals(3, $sentCount);
        $this->assertEquals(2, $failedCount);
    }
}