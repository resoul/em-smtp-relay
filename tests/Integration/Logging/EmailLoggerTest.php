<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Integration\Logging;

use Emercury\Smtp\Database\DatabaseManager;
use Emercury\Smtp\Events\EventManager;
use Emercury\Smtp\Logging\EmailLogger;
use Emercury\Smtp\Tests\IntegrationTestCase;

class EmailLoggerTest extends IntegrationTestCase
{
    private EmailLogger $logger;
    private DatabaseManager $dbManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbManager = new DatabaseManager();
        $events = EventManager::getInstance();
        $this->logger = new EmailLogger($this->dbManager, $events);
    }

    protected function tearDown(): void
    {
        $this->dbManager->dropTables();
        parent::tearDown();
    }

    public function testLogSentCreatesRecord(): void
    {
        global $wpdb;

        $emailData = [
            'to' => 'test@example.com',
            'subject' => 'Test Subject',
        ];

        $this->logger->logSent($emailData);

        $table = $this->dbManager->getLogsTableName();
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'sent'");

        $this->assertEquals(1, $count);
    }

    public function testLogFailedCreatesRecord(): void
    {
        global $wpdb;

        $emailData = [
            'to' => 'test@example.com',
            'subject' => 'Test Subject',
        ];

        $this->logger->logFailed($emailData, 'Connection timeout');

        $table = $this->dbManager->getLogsTableName();
        $record = $wpdb->get_row("SELECT * FROM $table WHERE status = 'failed'", ARRAY_A);

        $this->assertNotNull($record);
        $this->assertEquals('Connection timeout', $record['error_message']);
    }

    public function testGetRecentLogsReturnsRecords(): void
    {
        $this->logger->logSent(['to' => 'test1@example.com', 'subject' => 'Subject 1']);
        $this->logger->logSent(['to' => 'test2@example.com', 'subject' => 'Subject 2']);

        $logs = $this->logger->getRecentLogs(10);

        $this->assertCount(2, $logs);
    }

    public function testGetRecentLogsFiltersbyStatus(): void
    {
        $this->logger->logSent(['to' => 'test1@example.com', 'subject' => 'Subject 1']);
        $this->logger->logFailed(['to' => 'test2@example.com', 'subject' => 'Subject 2'], 'Error');

        $sentLogs = $this->logger->getRecentLogs(10, 'sent');

        $this->assertCount(1, $sentLogs);
        $this->assertEquals('sent', $sentLogs[0]['status']);
    }

    public function testGetStatisticsReturnsCorrectCounts(): void
    {
        $this->logger->logSent(['to' => 'test1@example.com', 'subject' => 'Subject 1']);
        $this->logger->logSent(['to' => 'test2@example.com', 'subject' => 'Subject 2']);
        $this->logger->logFailed(['to' => 'test3@example.com', 'subject' => 'Subject 3'], 'Error');

        $stats = $this->logger->getStatistics('today');

        $this->assertEquals(2, $stats['sent']);
        $this->assertEquals(1, $stats['failed']);
        $this->assertEquals(3, $stats['total']);
    }

    public function testClearOldLogsRemovesOldRecords(): void
    {
        global $wpdb;

        $table = $this->dbManager->getLogsTableName();

        // Insert old record
        $wpdb->insert($table, [
            'status' => 'sent',
            'recipient' => 'old@example.com',
            'subject' => 'Old Email',
            'created_at' => date('Y-m-d H:i:s', strtotime('-40 days'))
        ]);

        // Insert recent record
        $this->logger->logSent(['to' => 'recent@example.com', 'subject' => 'Recent Email']);

        $deleted = $this->logger->clearOldLogs(30);

        $this->assertEquals(1, $deleted);

        $remaining = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $this->assertEquals(1, $remaining);
    }
}