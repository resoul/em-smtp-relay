<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Database;

use Emercury\Smtp\Database\DatabaseManager;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class DatabaseManagerTest extends TestCase
{
    private $wpdb;
    private array $options = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('ABSPATH')) {
            define('ABSPATH', sys_get_temp_dir() . DIRECTORY_SEPARATOR);
        }

        $this->options = [];

        Functions\when('get_option')->alias(function($key, $default = false) {
            return $this->options[$key] ?? $default;
        });

        Functions\when('update_option')->alias(function($key, $value) {
            $this->options[$key] = $value;
            return true;
        });

        Functions\when('delete_option')->alias(function($key) {
            unset($this->options[$key]);
            return true;
        });

        $this->wpdb = Mockery::mock('wpdb');
        $this->wpdb->prefix = 'wp_';

        global $wpdb;
        $wpdb = $this->wpdb;
    }

    public function testConstructorCreatesTablesWhenDatabaseVersionIsOlder(): void
    {
        $this->options['em_smtp_db_version'] = '0';

        $this->wpdb
            ->shouldReceive('get_charset_collate')
            ->once()
            ->andReturn('DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        Functions\expect('dbDelta')
            ->twice()
            ->andReturn([]);

        new DatabaseManager();

        $this->assertEquals('1.0', $this->options['em_smtp_db_version']);
    }

    public function testConstructorDoesNotCreateTablesWhenDatabaseVersionIsCurrent(): void
    {
        $this->options['em_smtp_db_version'] = '1.0';

        Functions\expect('dbDelta')
            ->never();

        new DatabaseManager();
    }

    public function testConstructorCreatesTablesWhenDatabaseVersionIsMissing(): void
    {
        $this->wpdb
            ->shouldReceive('get_charset_collate')
            ->once()
            ->andReturn('DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        Functions\expect('dbDelta')
            ->twice()
            ->andReturn([]);

        new DatabaseManager();

        $this->assertEquals('1.0', $this->options['em_smtp_db_version']);
    }

    public function testGetLogsTableNameReturnsCorrectTableName(): void
    {
        $this->setupDatabaseManagerWithoutCreatingTables();

        $manager = new DatabaseManager();
        $tableName = $manager->getLogsTableName();

        $this->assertEquals('wp_em_smtp_logs', $tableName);
    }

    public function testGetStatsTableNameReturnsCorrectTableName(): void
    {
        $this->setupDatabaseManagerWithoutCreatingTables();

        $manager = new DatabaseManager();
        $tableName = $manager->getStatsTableName();

        $this->assertEquals('wp_em_smtp_statistics', $tableName);
    }

    public function testGetLogsTableNameWithDifferentPrefix(): void
    {
        $this->wpdb->prefix = 'custom_';
        $this->setupDatabaseManagerWithoutCreatingTables();

        $manager = new DatabaseManager();
        $tableName = $manager->getLogsTableName();

        $this->assertEquals('custom_em_smtp_logs', $tableName);
    }

    public function testGetStatsTableNameWithDifferentPrefix(): void
    {
        $this->wpdb->prefix = 'custom_';
        $this->setupDatabaseManagerWithoutCreatingTables();

        $manager = new DatabaseManager();
        $tableName = $manager->getStatsTableName();

        $this->assertEquals('custom_em_smtp_statistics', $tableName);
    }

    public function testDropTablesExecutesDropQueriesForBothTables(): void
    {
        $this->setupDatabaseManagerWithoutCreatingTables();

        $this->wpdb
            ->shouldReceive('query')
            ->once()
            ->with('DROP TABLE IF EXISTS wp_em_smtp_logs')
            ->andReturn(true);

        $this->wpdb
            ->shouldReceive('query')
            ->once()
            ->with('DROP TABLE IF EXISTS wp_em_smtp_statistics')
            ->andReturn(true);

        $manager = new DatabaseManager();
        $manager->dropTables();

        $this->assertArrayNotHasKey('em_smtp_db_version', $this->options);
    }

    public function testDropTablesDeletesVersionOption(): void
    {
        $this->options['em_smtp_db_version'] = '1.0';
        $this->setupDatabaseManagerWithoutCreatingTables();

        $this->wpdb
            ->shouldReceive('query')
            ->twice()
            ->andReturn(true);

        $manager = new DatabaseManager();
        $manager->dropTables();

        $this->assertArrayNotHasKey('em_smtp_db_version', $this->options);
    }

    public function testCreateTablesGeneratesCorrectLogsTableSchema(): void
    {
        $this->options['em_smtp_db_version'] = '0';

        $this->wpdb
            ->shouldReceive('get_charset_collate')
            ->once()
            ->andReturn('DEFAULT CHARACTER SET utf8mb4');

        $capturedSql = [];
        Functions\expect('dbDelta')
            ->twice()
            ->andReturnUsing(function($sql) use (&$capturedSql) {
                $capturedSql[] = $sql;
                return [];
            });

        new DatabaseManager();

        $this->assertCount(2, $capturedSql);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS wp_em_smtp_logs', $capturedSql[0]);
        $this->assertStringContainsString('id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT', $capturedSql[0]);
        $this->assertStringContainsString('status varchar(20) NOT NULL', $capturedSql[0]);
        $this->assertStringContainsString('recipient varchar(255) NOT NULL', $capturedSql[0]);
        $this->assertStringContainsString('subject text NOT NULL', $capturedSql[0]);
        $this->assertStringContainsString('error_message text NULL', $capturedSql[0]);
        $this->assertStringContainsString('metadata longtext NULL', $capturedSql[0]);
        $this->assertStringContainsString('created_at datetime NOT NULL', $capturedSql[0]);
        $this->assertStringContainsString('PRIMARY KEY (id)', $capturedSql[0]);
        $this->assertStringContainsString('KEY status (status)', $capturedSql[0]);
        $this->assertStringContainsString('KEY created_at (created_at)', $capturedSql[0]);
        $this->assertStringContainsString('KEY status_date (status, created_at)', $capturedSql[0]);
    }

    public function testCreateTablesGeneratesCorrectStatsTableSchema(): void
    {
        $this->options['em_smtp_db_version'] = '0';

        $this->wpdb
            ->shouldReceive('get_charset_collate')
            ->once()
            ->andReturn('DEFAULT CHARACTER SET utf8mb4');

        $capturedSql = [];
        Functions\expect('dbDelta')
            ->twice()
            ->andReturnUsing(function($sql) use (&$capturedSql) {
                $capturedSql[] = $sql;
                return [];
            });

        new DatabaseManager();

        $this->assertCount(2, $capturedSql);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS wp_em_smtp_statistics', $capturedSql[1]);
        $this->assertStringContainsString('id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT', $capturedSql[1]);
        $this->assertStringContainsString('date date NOT NULL', $capturedSql[1]);
        $this->assertStringContainsString('hour tinyint(2) NOT NULL DEFAULT 0', $capturedSql[1]);
        $this->assertStringContainsString('sent_count int(11) NOT NULL DEFAULT 0', $capturedSql[1]);
        $this->assertStringContainsString('failed_count int(11) NOT NULL DEFAULT 0', $capturedSql[1]);
        $this->assertStringContainsString('PRIMARY KEY (id)', $capturedSql[1]);
        $this->assertStringContainsString('UNIQUE KEY date_hour (date, hour)', $capturedSql[1]);
    }

    public function testCreateTablesIncludesCharsetCollation(): void
    {
        $this->options['em_smtp_db_version'] = '0';

        $this->wpdb
            ->shouldReceive('get_charset_collate')
            ->once()
            ->andReturn('DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');

        $capturedSql = [];
        Functions\expect('dbDelta')
            ->twice()
            ->andReturnUsing(function($sql) use (&$capturedSql) {
                $capturedSql[] = $sql;
                return [];
            });

        new DatabaseManager();

        foreach ($capturedSql as $sql) {
            $this->assertStringContainsString('DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci', $sql);
        }
    }

    public function testMultipleInstancesUseSameDatabaseVersion(): void
    {
        $this->setupDatabaseManagerWithoutCreatingTables();

        $manager1 = new DatabaseManager();
        $manager2 = new DatabaseManager();

        $this->assertEquals(
            $manager1->getLogsTableName(),
            $manager2->getLogsTableName()
        );

        $this->assertEquals(
            $manager1->getStatsTableName(),
            $manager2->getStatsTableName()
        );
    }

    public function testDropTablesWithCustomPrefix(): void
    {
        $this->wpdb->prefix = 'custom_';
        $this->setupDatabaseManagerWithoutCreatingTables();

        $this->wpdb
            ->shouldReceive('query')
            ->once()
            ->with('DROP TABLE IF EXISTS custom_em_smtp_logs')
            ->andReturn(true);

        $this->wpdb
            ->shouldReceive('query')
            ->once()
            ->with('DROP TABLE IF EXISTS custom_em_smtp_statistics')
            ->andReturn(true);

        $manager = new DatabaseManager();
        $manager->dropTables();
    }

    public function testConstructorRequiresDbDeltaFunction(): void
    {
        $this->options['em_smtp_db_version'] = '0';

        $this->wpdb
            ->shouldReceive('get_charset_collate')
            ->once()
            ->andReturn('');

        Functions\expect('dbDelta')
            ->twice()
            ->andReturn([]);

        $manager = new DatabaseManager();

        $this->assertInstanceOf(DatabaseManager::class, $manager);
    }

    public function testTableNamesAreConsistentAcrossMethodCalls(): void
    {
        $this->setupDatabaseManagerWithoutCreatingTables();

        $manager = new DatabaseManager();

        $logsTable1 = $manager->getLogsTableName();
        $logsTable2 = $manager->getLogsTableName();
        $statsTable1 = $manager->getStatsTableName();
        $statsTable2 = $manager->getStatsTableName();

        $this->assertSame($logsTable1, $logsTable2);
        $this->assertSame($statsTable1, $statsTable2);
    }

    public function testVersionOptionIsSetAfterTableCreation(): void
    {
        $this->assertArrayNotHasKey('em_smtp_db_version', $this->options);

        $this->wpdb
            ->shouldReceive('get_charset_collate')
            ->once()
            ->andReturn('');

        Functions\expect('dbDelta')
            ->twice()
            ->andReturn([]);

        new DatabaseManager();

        $this->assertArrayHasKey('em_smtp_db_version', $this->options);
        $this->assertEquals('1.0', $this->options['em_smtp_db_version']);
    }

    public function testUpgradeFromOldVersion(): void
    {
        $this->options['em_smtp_db_version'] = '0.5';

        $this->wpdb
            ->shouldReceive('get_charset_collate')
            ->once()
            ->andReturn('');

        Functions\expect('dbDelta')
            ->twice()
            ->andReturn([]);

        new DatabaseManager();

        $this->assertEquals('1.0', $this->options['em_smtp_db_version']);
    }

    public function testNoUpgradeForCurrentVersion(): void
    {
        $this->options['em_smtp_db_version'] = '1.0';

        Functions\expect('dbDelta')
            ->never();

        new DatabaseManager();

        $this->assertEquals('1.0', $this->options['em_smtp_db_version']);
    }

    public function testNoUpgradeForNewerVersion(): void
    {
        $this->options['em_smtp_db_version'] = '2.0';

        Functions\expect('dbDelta')
            ->never();

        new DatabaseManager();

        $this->assertEquals('2.0', $this->options['em_smtp_db_version']);
    }

    public function testEmptyPrefixHandling(): void
    {
        $this->wpdb->prefix = '';
        $this->setupDatabaseManagerWithoutCreatingTables();

        $manager = new DatabaseManager();

        $this->assertEquals('em_smtp_logs', $manager->getLogsTableName());
        $this->assertEquals('em_smtp_statistics', $manager->getStatsTableName());
    }

    public function testDropTablesHandlesQueryFailure(): void
    {
        $this->setupDatabaseManagerWithoutCreatingTables();

        $this->wpdb
            ->shouldReceive('query')
            ->twice()
            ->andReturn(false);

        $manager = new DatabaseManager();
        $manager->dropTables();

        $this->assertArrayNotHasKey('em_smtp_db_version', $this->options);
    }

    private function setupDatabaseManagerWithoutCreatingTables(): void
    {
        $this->options['em_smtp_db_version'] = '1.0';
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = null;

        parent::tearDown();
    }
}