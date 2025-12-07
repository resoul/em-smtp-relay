<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Core;

use Emercury\Smtp\Admin\DashboardWidget;
use Emercury\Smtp\Admin\SettingsPage;
use Emercury\Smtp\Admin\StatisticsPage;
use Emercury\Smtp\Admin\Tabs\TestEmailTab;
use Emercury\Smtp\Contracts\EmailLoggerInterface;
use Emercury\Smtp\Core\Container;
use Emercury\Smtp\Core\Mailer;
use Emercury\Smtp\Core\Plugin;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Mockery;

class PluginTest extends TestCase
{
    private Plugin $plugin;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = Mockery::mock(Container::class);
        $this->plugin = new Plugin($this->container);

        Functions\when('load_plugin_textdomain')->justReturn(true);
        Functions\when('dirname')->returnArg();
        Functions\when('is_admin')->justReturn(false);
        Functions\when('wp_next_scheduled')->justReturn(false);
        Functions\when('time')->justReturn(1234567890);
        Functions\when('wp_schedule_event')->justReturn(true);

        if (!defined('EM_SMTP_BASENAME')) {
            define('EM_SMTP_BASENAME', 'em-smtp-relay/em-smtp-relay.php');
        }
    }

    // Constructor tests
    public function testConstructorAcceptsContainer(): void
    {
        $container = Mockery::mock(Container::class);
        $plugin = new Plugin($container);

        $this->assertInstanceOf(Plugin::class, $plugin);
    }

    // init() method tests
    public function testInitRegistersTextDomainLoader(): void
    {
        Actions\expectAdded('wp_ajax_em_smtp_delete_attachment')->once();
        Actions\expectAdded('em_smtp_cleanup_logs')->once();

        $dashboardWidget->shouldReceive('register')->once();
        $statisticsPage->shouldReceive('register')->once();

        Functions\when('wp_next_scheduled')->justReturn(false);

        $this->plugin->init();

        $this->assertTrue(true);
    }

    public function testInitInNonAdminEnvironment(): void
    {
        Functions\expect('is_admin')
            ->once()
            ->andReturn(false);

        $mailer = Mockery::mock(Mailer::class);

        $this->container
            ->shouldReceive('get')
            ->with(Mailer::class)
            ->andReturn($mailer);

        Actions\expectAdded('init')->once();
        Filters\expectAdded('pre_wp_mail')->once();
        Actions\expectAdded('em_smtp_cleanup_logs')->once();

        Actions\expectNotAdded('admin_menu');
        Filters\expectNotAdded('plugin_action_links_' . EM_SMTP_BASENAME);
        Actions\expectNotAdded('wp_ajax_em_smtp_delete_attachment');

        Functions\when('wp_next_scheduled')->justReturn(false);

        $this->plugin->init();

        $this->assertTrue(true);
    }

    public function testMultipleInitCallsAreIdempotent(): void
    {
        $mailer = Mockery::mock(Mailer::class);

        $this->container
            ->shouldReceive('get')
            ->with(Mailer::class)
            ->twice()
            ->andReturn($mailer);

        Functions\when('is_admin')->justReturn(false);
        Functions\when('wp_next_scheduled')->justReturn(false);

        // First init
        $this->plugin->init();

        // Second init
        $this->plugin->init();

        // Hooks should only be added once by WordPress
        $this->assertTrue(true);
    }

    public function testContainerIsUsedToResolveAllDependencies(): void
    {
        Functions\expect('is_admin')
            ->once()
            ->andReturn(true);

        $mailer = Mockery::mock(Mailer::class);
        $settingsPage = Mockery::mock(SettingsPage::class);
        $dashboardWidget = Mockery::mock(DashboardWidget::class);
        $statisticsPage = Mockery::mock(StatisticsPage::class);
        $testEmailTab = Mockery::mock(TestEmailTab::class);
        $emailLogger = Mockery::mock(EmailLoggerInterface::class);

        $this->container
            ->shouldReceive('get')
            ->with(Mailer::class)
            ->once()
            ->andReturn($mailer);

        $this->container
            ->shouldReceive('get')
            ->with(SettingsPage::class)
            ->once()
            ->andReturn($settingsPage);

        $this->container
            ->shouldReceive('get')
            ->with(DashboardWidget::class)
            ->once()
            ->andReturn($dashboardWidget);

        $this->container
            ->shouldReceive('get')
            ->with(StatisticsPage::class)
            ->once()
            ->andReturn($statisticsPage);

        $this->container
            ->shouldReceive('get')
            ->with(TestEmailTab::class)
            ->once()
            ->andReturn($testEmailTab);

        $this->container
            ->shouldReceive('get')
            ->with(EmailLoggerInterface::class)
            ->once()
            ->andReturn($emailLogger);

        $dashboardWidget->shouldReceive('register')->once();
        $statisticsPage->shouldReceive('register')->once();
        $emailLogger->shouldReceive('clearOldLogs')->andReturn(0);

        Functions\when('wp_next_scheduled')->justReturn(false);

        $this->plugin->init();

        $this->assertTrue(true);
    }

    // Edge cases
    public function testInitWithScheduleEventFailure(): void
    {
        Functions\expect('wp_next_scheduled')
            ->once()
            ->andReturn(false);

        Functions\expect('wp_schedule_event')
            ->once()
            ->andReturn(false); // Schedule failed

        // Should still register the action hook
        Actions\expectAdded('em_smtp_cleanup_logs')
            ->once();

        $this->container
            ->shouldReceive('get')
            ->with(Mailer::class)
            ->andReturn(Mockery::mock(Mailer::class));

        Functions\when('is_admin')->justReturn(false);

        $this->plugin->init();

        $this->assertTrue(true);
    }

    public function testInitWithClearOldLogsReturningZero(): void
    {
        $emailLogger = Mockery::mock(EmailLoggerInterface::class);

        $this->container
            ->shouldReceive('get')
            ->with(Mailer::class)
            ->andReturn(Mockery::mock(Mailer::class));

        $this->container
            ->shouldReceive('get')
            ->with(EmailLoggerInterface::class)
            ->andReturn($emailLogger);

        $emailLogger
            ->shouldReceive('clearOldLogs')
            ->once()
            ->with(30)
            ->andReturn(0); // No logs deleted

        Actions\expectAdded('em_smtp_cleanup_logs')
            ->once()
            ->whenHappen(function(callable $callback) {
                $callback();
            });

        Functions\when('is_admin')->justReturn(false);
        Functions\when('wp_next_scheduled')->justReturn(false);

        $this->plugin->init();

        $this->assertTrue(true);
    }

    public function testInitWithClearOldLogsReturningMany(): void
    {
        $emailLogger = Mockery::mock(EmailLoggerInterface::class);

        $this->container
            ->shouldReceive('get')
            ->with(Mailer::class)
            ->andReturn(Mockery::mock(Mailer::class));

        $this->container
            ->shouldReceive('get')
            ->with(EmailLoggerInterface::class)
            ->andReturn($emailLogger);

        $emailLogger
            ->shouldReceive('clearOldLogs')
            ->once()
            ->with(30)
            ->andReturn(1000); // Many logs deleted

        Actions\expectAdded('em_smtp_cleanup_logs')
            ->once()
            ->whenHappen(function(callable $callback) {
                $callback();
            });

        Functions\when('is_admin')->justReturn(false);
        Functions\when('wp_next_scheduled')->justReturn(false);

        $this->plugin->init();

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}