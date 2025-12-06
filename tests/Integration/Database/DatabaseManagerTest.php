<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Integration\Database;

use Emercury\Smtp\Database\DatabaseManager;
use Emercury\Smtp\Tests\IntegrationTestCase;

class DatabaseManagerTest extends IntegrationTestCase
{
    private DatabaseManager $dbManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dbManager = new DatabaseManager();
    }

    protected function tearDown(): void
    {
        $this->dbManager->dropTables();
        parent::tearDown();
    }

    public function testTablesAreCreated(): void
    {
        global $wpdb;

        $logsTable = $this->dbManager->getLogsTableName();
        $statsTable = $this->dbManager->getStatsTableName();

        $logsExists = $wpdb->get_var("SHOW TABLES LIKE '$logsTable'");
        $statsExists = $wpdb->get_var("SHOW TABLES LIKE '$statsTable'");

        $this->assertEquals($logsTable, $logsExists);
        $this->assertEquals($statsTable, $statsExists);
    }

    public function testDropTablesRemovesTables(): void
    {
        global $wpdb;

        $this->dbManager->dropTables();

        $logsTable = $this->dbManager->getLogsTableName();
        $statsTable = $this->dbManager->getStatsTableName();

        $logsExists = $wpdb->get_var("SHOW TABLES LIKE '$logsTable'");
        $statsExists = $wpdb->get_var("SHOW TABLES LIKE '$statsTable'");

        $this->assertNull($logsExists);
        $this->assertNull($statsExists);
    }
}