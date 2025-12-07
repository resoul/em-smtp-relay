<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Logging;

use Emercury\Smtp\Logging\EmailLogger;
use Emercury\Smtp\Database\DatabaseManager;
use Emercury\Smtp\Events\EventManager;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class EmailLoggerTest extends TestCase
{
    private EmailLogger $logger;
    private $dbManager;
    private $eventManager;
    private $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('ARRAY_A')) {
            define('ARRAY_A', 'ARRAY_A');
        }

        $this->dbManager = $this->mock(DatabaseManager::class);
        $this->eventManager = $this->mock(EventManager::class);

        $this->wpdb = Mockery::mock('wpdb');
        global $wpdb;
        $wpdb = $this->wpdb;

        $this->logger = new EmailLogger($this->dbManager, $this->eventManager);
    }

    public function testLogSentInsertsDataAndDispatchesEvent(): void
    {
        $emailData = [
            'to' => 'test@example.com',
            'subject' => 'Test Subject',
            'message' => 'Test message'
        ];

        $this->dbManager
            ->shouldReceive('getLogsTableName')
            ->once()
            ->andReturn('wp_em_smtp_logs');

        $this->dbManager
            ->shouldReceive('getStatsTableName')
            ->once()
            ->andReturn('wp_em_smtp_stats');

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_json_encode')->alias('json_encode');
        Functions\when('current_time')->alias(function($format) {
            if ($format === 'mysql') {
                return '2024-01-15 10:30:00';
            }
            if ($format === 'Y-m-d') {
                return '2024-01-15';
            }
            if ($format === 'H') {
                return '10';
            }
            return '2024-01-15 10:30:00';
        });

        $this->wpdb
            ->shouldReceive('insert')
            ->once()
            ->with('wp_em_smtp_logs', [
                'status' => 'sent',
                'recipient' => 'test@example.com',
                'subject' => 'Test Subject',
                'metadata' => json_encode($emailData),
                'created_at' => '2024-01-15 10:30:00',
            ])
            ->andReturn(1);

        $this->wpdb
            ->shouldReceive('prepare')
            ->once()
            ->andReturn("INSERT INTO wp_em_smtp_stats ...");

        $this->wpdb
            ->shouldReceive('query')
            ->once()
            ->andReturn(1);

        $this->eventManager
            ->shouldReceive('dispatch')
            ->once()
            ->with('email_sent', $emailData);

        $this->logger->logSent($emailData);
    }

    public function testLogSentHandlesArrayRecipients(): void
    {
        $emailData = [
            'to' => ['test1@example.com', 'test2@example.com'],
            'subject' => 'Test Subject'
        ];

        $this->dbManager
            ->shouldReceive('getLogsTableName')
            ->andReturn('wp_em_smtp_logs');

        $this->dbManager
            ->shouldReceive('getStatsTableName')
            ->andReturn('wp_em_smtp_stats');

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_json_encode')->alias('json_encode');
        Functions\when('current_time')->justReturn('2024-01-15 10:30:00');

        $this->wpdb
            ->shouldReceive('insert')
            ->once()
            ->with('wp_em_smtp_logs', Mockery::on(function($data) {
                return $data['recipient'] === 'test1@example.com, test2@example.com';
            }))
            ->andReturn(1);

        $this->wpdb->shouldReceive('prepare')->andReturn('');
        $this->wpdb->shouldReceive('query')->andReturn(1);
        $this->eventManager->shouldReceive('dispatch');

        $this->logger->logSent($emailData);
    }

    public function testLogSentHandlesMissingFields(): void
    {
        $emailData = [];

        $this->dbManager->shouldReceive('getLogsTableName')->andReturn('wp_em_smtp_logs');
        $this->dbManager->shouldReceive('getStatsTableName')->andReturn('wp_em_smtp_stats');

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_json_encode')->alias('json_encode');
        Functions\when('current_time')->justReturn('2024-01-15 10:30:00');

        $this->wpdb
            ->shouldReceive('insert')
            ->once()
            ->with('wp_em_smtp_logs', [
                'status' => 'sent',
                'recipient' => '',
                'subject' => '',
                'metadata' => '[]',
                'created_at' => '2024-01-15 10:30:00',
            ])
            ->andReturn(1);

        $this->wpdb->shouldReceive('prepare')->andReturn('');
        $this->wpdb->shouldReceive('query')->andReturn(1);
        $this->eventManager->shouldReceive('dispatch');

        $this->logger->logSent($emailData);
    }

    public function testLogFailedInsertsDataWithErrorAndDispatchesEvent(): void
    {
        $emailData = [
            'to' => 'test@example.com',
            'subject' => 'Test Subject'
        ];
        $error = 'SMTP connection failed';

        $this->dbManager->shouldReceive('getLogsTableName')->andReturn('wp_em_smtp_logs');
        $this->dbManager->shouldReceive('getStatsTableName')->andReturn('wp_em_smtp_stats');

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_json_encode')->alias('json_encode');
        Functions\when('current_time')->justReturn('2024-01-15 10:30:00');

        $this->wpdb
            ->shouldReceive('insert')
            ->once()
            ->with('wp_em_smtp_logs', [
                'status' => 'failed',
                'recipient' => 'test@example.com',
                'subject' => 'Test Subject',
                'error_message' => 'SMTP connection failed',
                'metadata' => json_encode($emailData),
                'created_at' => '2024-01-15 10:30:00',
            ])
            ->andReturn(1);

        $this->wpdb->shouldReceive('prepare')->andReturn('');
        $this->wpdb->shouldReceive('query')->andReturn(1);

        $this->eventManager
            ->shouldReceive('dispatch')
            ->once()
            ->with('email_failed', $emailData, $error);

        $this->logger->logFailed($emailData, $error);
    }

    public function testGetRecentLogsReturnsAllLogsWhenNoStatusProvided(): void
    {
        $this->dbManager->shouldReceive('getLogsTableName')->andReturn('wp_em_smtp_logs');

        $expectedLogs = [
            ['id' => 1, 'status' => 'sent'],
            ['id' => 2, 'status' => 'failed']
        ];

        $this->wpdb
            ->shouldReceive('prepare')
            ->once()
            ->with(Mockery::on(function($sql) {
                return strpos($sql, 'ORDER BY created_at DESC LIMIT %d') !== false;
            }), 50)
            ->andReturn('SELECT * FROM wp_em_smtp_logs ORDER BY created_at DESC LIMIT 50');

        $this->wpdb
            ->shouldReceive('get_results')
            ->once()
            ->with('SELECT * FROM wp_em_smtp_logs ORDER BY created_at DESC LIMIT 50', ARRAY_A)
            ->andReturn($expectedLogs);

        $result = $this->logger->getRecentLogs(50);

        $this->assertEquals($expectedLogs, $result);
    }

    public function testGetRecentLogsFiltersbyStatus(): void
    {
        $this->dbManager->shouldReceive('getLogsTableName')->andReturn('wp_em_smtp_logs');

        $expectedLogs = [
            ['id' => 1, 'status' => 'sent']
        ];

        $this->wpdb
            ->shouldReceive('prepare')
            ->twice()
            ->andReturnUsing(function($sql, ...$args) {
                if (count($args) === 1 && $args[0] === 'sent') {
                    return 'SELECT * FROM wp_em_smtp_logs WHERE status = "sent"';
                }
                return 'SELECT * FROM wp_em_smtp_logs WHERE status = "sent" ORDER BY created_at DESC LIMIT 10';
            });

        $this->wpdb
            ->shouldReceive('get_results')
            ->once()
            ->andReturn($expectedLogs);

        $result = $this->logger->getRecentLogs(10, 'sent');

        $this->assertEquals($expectedLogs, $result);
    }

    public function testClearOldLogsDeletesRecordsOlderThanSpecifiedDays(): void
    {
        $this->dbManager->shouldReceive('getLogsTableName')->andReturn('wp_em_smtp_logs');

        $cutoffDate = '2023-12-16 10:30:00';

        $this->wpdb
            ->shouldReceive('prepare')
            ->once()
            ->with(
                Mockery::type('string'),
                Mockery::type('string')
            )
            ->andReturn('DELETE FROM wp_em_smtp_logs WHERE created_at < "' . $cutoffDate . '"');

        $this->wpdb
            ->shouldReceive('query')
            ->once()
            ->with('DELETE FROM wp_em_smtp_logs WHERE created_at < "' . $cutoffDate . '"')
            ->andReturn(15);

        $result = $this->logger->clearOldLogs(30);

        $this->assertEquals(15, $result);
    }

    public function testClearOldLogsReturnsZeroWhenNoRecordsDeleted(): void
    {
        $this->dbManager->shouldReceive('getLogsTableName')->andReturn('wp_em_smtp_logs');

        $this->wpdb->shouldReceive('prepare')->andReturn('');
        $this->wpdb->shouldReceive('query')->andReturn(false);

        $result = $this->logger->clearOldLogs(30);

        $this->assertEquals(0, $result);
    }

    public function testGetStatisticsForToday(): void
    {
        $this->dbManager->shouldReceive('getLogsTableName')->andReturn('wp_em_smtp_logs');

        Functions\when('current_time')->justReturn('2024-01-15 00:00:00');

        $this->wpdb
            ->shouldReceive('prepare')
            ->once()
            ->andReturn('SELECT COUNT...');

        $this->wpdb
            ->shouldReceive('get_row')
            ->once()
            ->with('SELECT COUNT...', ARRAY_A)
            ->andReturn([
                'sent' => '80',
                'failed' => '20',
                'total' => '100'
            ]);

        $result = $this->logger->getStatistics('today');

        $this->assertEquals([
            'sent' => 80,
            'failed' => 20,
            'total' => 100,
            'success_rate' => 80.0,
            'failure_rate' => 20.0,
        ], $result);
    }

    public function testGetStatisticsForWeek(): void
    {
        $this->dbManager->shouldReceive('getLogsTableName')->andReturn('wp_em_smtp_logs');

        $weekAgo = '2024-01-08 00:00:00';

        $this->wpdb
            ->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), Mockery::type('string'))
            ->andReturn('SELECT COUNT...');

        $this->wpdb
            ->shouldReceive('get_row')
            ->once()
            ->with('SELECT COUNT...', ARRAY_A)
            ->andReturn([
                'sent' => '150',
                'failed' => '50',
                'total' => '200'
            ]);

        $result = $this->logger->getStatistics('week');

        $this->assertEquals([
            'sent' => 150,
            'failed' => 50,
            'total' => 200,
            'success_rate' => 75.0,
            'failure_rate' => 25.0,
        ], $result);
    }

    public function testGetStatisticsForMonth(): void
    {
        $this->dbManager->shouldReceive('getLogsTableName')->andReturn('wp_em_smtp_logs');

        $this->wpdb
            ->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), Mockery::type('string'))
            ->andReturn('SELECT COUNT...');

        $this->wpdb
            ->shouldReceive('get_row')
            ->once()
            ->with('SELECT COUNT...', ARRAY_A)
            ->andReturn([
                'sent' => '300',
                'failed' => '100',
                'total' => '400'
            ]);

        $result = $this->logger->getStatistics('month');

        $this->assertEquals([
            'sent' => 300,
            'failed' => 100,
            'total' => 400,
            'success_rate' => 75.0,
            'failure_rate' => 25.0,
        ], $result);
    }

    public function testGetStatisticsForAll(): void
    {
        $this->dbManager->shouldReceive('getLogsTableName')->andReturn('wp_em_smtp_logs');

        $this->wpdb
            ->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), '1970-01-01 00:00:00')
            ->andReturn('SELECT COUNT...');

        $this->wpdb
            ->shouldReceive('get_row')
            ->once()
            ->with('SELECT COUNT...', ARRAY_A)
            ->andReturn([
                'sent' => '1000',
                'failed' => '200',
                'total' => '1200'
            ]);

        $result = $this->logger->getStatistics('all');

        $this->assertEquals([
            'sent' => 1000,
            'failed' => 200,
            'total' => 1200,
            'success_rate' => 83.33,
            'failure_rate' => 16.67,
        ], $result);
    }

    public function testGetStatisticsHandlesZeroTotal(): void
    {
        $this->dbManager->shouldReceive('getLogsTableName')->andReturn('wp_em_smtp_logs');

        Functions\when('current_time')->justReturn('2024-01-15 00:00:00');

        $this->wpdb->shouldReceive('prepare')->andReturn('SELECT COUNT...');
        $this->wpdb
            ->shouldReceive('get_row')
            ->once()
            ->with('SELECT COUNT...', ARRAY_A)
            ->andReturn([
                'sent' => '0',
                'failed' => '0',
                'total' => '0'
            ]);

        $result = $this->logger->getStatistics('today');

        $this->assertEquals([
            'sent' => 0,
            'failed' => 0,
            'total' => 0,
            'success_rate' => 0,
            'failure_rate' => 0,
        ], $result);
    }

    public function testGetStatisticsHandlesNullResults(): void
    {
        $this->dbManager->shouldReceive('getLogsTableName')->andReturn('wp_em_smtp_logs');

        Functions\when('current_time')->justReturn('2024-01-15 00:00:00');

        $this->wpdb->shouldReceive('prepare')->andReturn('SELECT COUNT...');
        $this->wpdb
            ->shouldReceive('get_row')
            ->once()
            ->with('SELECT COUNT...', ARRAY_A)
            ->andReturn([
                'sent' => null,
                'failed' => null,
                'total' => null
            ]);

        $result = $this->logger->getStatistics('today');

        $this->assertEquals([
            'sent' => 0,
            'failed' => 0,
            'total' => 0,
            'success_rate' => 0,
            'failure_rate' => 0,
        ], $result);
    }

    public function testGetHourlyStatisticsReturnsDataForSpecifiedDays(): void
    {
        $this->dbManager->shouldReceive('getStatsTableName')->andReturn('wp_em_smtp_stats');

        $expectedData = [
            ['date' => '2024-01-15', 'hour' => 10, 'sent_count' => 50, 'failed_count' => 5],
            ['date' => '2024-01-15', 'hour' => 11, 'sent_count' => 45, 'failed_count' => 3],
        ];

        $this->wpdb
            ->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), Mockery::type('string'))
            ->andReturn('SELECT...');

        $this->wpdb
            ->shouldReceive('get_results')
            ->once()
            ->with('SELECT...', ARRAY_A)
            ->andReturn($expectedData);

        $result = $this->logger->getHourlyStatistics(7);

        $this->assertEquals($expectedData, $result);
    }

    public function testGetHourlyStatisticsReturnsEmptyArrayWhenNoData(): void
    {
        $this->dbManager->shouldReceive('getStatsTableName')->andReturn('wp_em_smtp_stats');

        $this->wpdb
            ->shouldReceive('prepare')
            ->once()
            ->andReturn('SELECT...');

        $this->wpdb
            ->shouldReceive('get_results')
            ->once()
            ->with('SELECT...', ARRAY_A)
            ->andReturn(false);

        $result = $this->logger->getHourlyStatistics(7);

        $this->assertEquals([], $result);
    }

    public function testGetDailyStatisticsReturnsAggregatedDataForSpecifiedDays(): void
    {
        $this->dbManager->shouldReceive('getStatsTableName')->andReturn('wp_em_smtp_stats');

        $expectedData = [
            ['date' => '2024-01-15', 'sent' => '100', 'failed' => '10', 'total' => '110'],
            ['date' => '2024-01-16', 'sent' => '120', 'failed' => '8', 'total' => '128'],
        ];

        $this->wpdb
            ->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), Mockery::type('string'))
            ->andReturn('SELECT...');

        $this->wpdb
            ->shouldReceive('get_results')
            ->once()
            ->with('SELECT...', ARRAY_A)
            ->andReturn($expectedData);

        $result = $this->logger->getDailyStatistics(30);

        $this->assertEquals($expectedData, $result);
    }

    public function testGetDailyStatisticsReturnsEmptyArrayWhenNoData(): void
    {
        $this->dbManager->shouldReceive('getStatsTableName')->andReturn('wp_em_smtp_stats');

        $this->wpdb
            ->shouldReceive('prepare')
            ->once()
            ->andReturn('SELECT...');

        $this->wpdb
            ->shouldReceive('get_results')
            ->once()
            ->with('SELECT...', ARRAY_A)
            ->andReturn(null);

        $result = $this->logger->getDailyStatistics(30);

        $this->assertEquals([], $result);
    }

    public function testGetLogsTableNameReturnsDatabaseManagerTableName(): void
    {
        $this->dbManager
            ->shouldReceive('getLogsTableName')
            ->once()
            ->andReturn('wp_em_smtp_logs');

        $result = $this->logger->getLogsTableName();

        $this->assertEquals('wp_em_smtp_logs', $result);
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = null;

        parent::tearDown();
    }
}