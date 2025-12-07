<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Admin;

use Emercury\Smtp\Admin\DashboardWidget;
use Emercury\Smtp\Contracts\EmailStatisticsInterface;
use Emercury\Smtp\Contracts\ConfigInterface;
use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class DashboardWidgetTest extends TestCase
{
    private DashboardWidget $widget;
    private $statistics;
    private $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statistics = Mockery::mock(EmailStatisticsInterface::class);
        $this->config = Mockery::mock(ConfigInterface::class);

        $this->widget = new DashboardWidget(
            $this->statistics,
            $this->config
        );

        Functions\when('__')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_url')->returnArg();
        Functions\when('human_time_diff')->justReturn('5 minutes');
        Functions\when('wp_trim_words')->returnArg();

        if (!defined('EM_SMTP_PATH')) {
            define('EM_SMTP_PATH', sys_get_temp_dir() . '/em-smtp-test/');
        }

        $this->createTestDirectories();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestDirectories();
        parent::tearDown();
    }

    private function createTestDirectories(): void
    {
        $path = EM_SMTP_PATH . 'templates/admin/';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    private function cleanupTestDirectories(): void
    {
        $path = EM_SMTP_PATH;
        if (is_dir($path)) {
            $this->rrmdir($path);
        }
    }

    private function rrmdir($dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . "/" . $object)) {
                    $this->rrmdir($dir . "/" . $object);
                } else {
                    unlink($dir . "/" . $object);
                }
            }
        }
        rmdir($dir);
    }

    private function createMockTemplate(string $content = '<!-- MOCK_DASHBOARD_WIDGET -->'): void
    {
        $template = EM_SMTP_PATH . 'templates/admin/dashboard-widget.php';
        file_put_contents($template, $content);
    }

    public function testRegisterAddsActionHooks(): void
    {
        Functions\expect('add_action')
            ->once()
            ->with('wp_dashboard_setup', [$this->widget, 'addWidget']);

        Functions\expect('add_action')
            ->once()
            ->with('admin_enqueue_scripts', [$this->widget, 'enqueueStyles']);

        $this->widget->register();
    }

    public function testAddWidgetCallsWpAddDashboardWidget(): void
    {
        Functions\expect('wp_add_dashboard_widget')
            ->once()
            ->with(
                'em_smtp_dashboard_widget',
                'Emercury SMTP Status',
                [$this->widget, 'render'],
                null,
                null,
                'side',
                'high'
            );

        $this->widget->addWidget();
    }

    public function testEnqueueStylesOnlyOnDashboardPage(): void
    {
        $screen = (object)[
            'id' => 'dashboard'
        ];

        Functions\expect('get_current_screen')
            ->once()
            ->andReturn($screen);

        Functions\expect('wp_add_inline_style')
            ->once()
            ->with('dashboard', Mockery::type('string'));

        $this->widget->enqueueStyles();
    }

    public function testEnqueueStylesDoesNotRunOnNonDashboardPage(): void
    {
        $screen = (object)[
            'id' => 'settings'
        ];

        Functions\expect('get_current_screen')
            ->once()
            ->andReturn($screen);

        Functions\expect('wp_add_inline_style')
            ->never();

        $this->widget->enqueueStyles();
    }

    public function testEnqueueStylesHandlesNullScreen(): void
    {
        Functions\expect('get_current_screen')
            ->once()
            ->andReturn(null);

        Functions\expect('wp_add_inline_style')
            ->never();

        $this->widget->enqueueStyles();
    }

    public function testRenderShowsActiveStatusWhenConfigured(): void
    {
        $summary = [
            'today' => [
                'sent' => 50,
                'failed' => 5,
                'success_rate' => 90.91
            ],
            'week' => [
                'total' => 375
            ],
            'month' => [
                'total' => 1600,
                'success_rate' => 93.75
            ]
        ];

        $recentLogs = [
            [
                'status' => 'sent',
                'subject' => 'Test Email',
                'recipient' => 'test@example.com',
                'created_at' => '2024-01-15 10:30:00'
            ]
        ];

        $settings = new SmtpSettingsDTO(
            'user@example.com',
            'password',
            'tls',
            'from@example.com',
            'From Name'
        );

        $this->statistics
            ->shouldReceive('getSummary')
            ->once()
            ->andReturn($summary);

        $this->statistics
            ->shouldReceive('getRecentLogs')
            ->once()
            ->with(5)
            ->andReturn($recentLogs);

        $this->config
            ->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($settings);

        Functions\expect('current_time')
            ->once()
            ->with('timestamp')
            ->andReturn(strtotime('2024-01-15 10:35:00'));

        $this->createMockTemplate('<?php echo $isConfigured ? "Active" : "Not Configured"; ?>');

        ob_start();
        $this->widget->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('Active', $output);
        $this->assertStringNotContainsString('Not Configured', $output);
    }

    public function testRenderShowsInactiveStatusWhenNotConfigured(): void
    {
        $summary = [
            'today' => ['sent' => 0, 'failed' => 0, 'success_rate' => 0],
            'week' => ['total' => 0],
            'month' => ['total' => 0, 'success_rate' => 0]
        ];

        $settings = new SmtpSettingsDTO();

        $this->statistics
            ->shouldReceive('getSummary')
            ->once()
            ->andReturn($summary);

        $this->statistics
            ->shouldReceive('getRecentLogs')
            ->once()
            ->with(5)
            ->andReturn([]);

        $this->config
            ->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($settings);

        $this->createMockTemplate('<?php echo $isConfigured ? "Active" : "Not Configured"; ?>');

        ob_start();
        $this->widget->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('Not Configured', $output);
        $this->assertStringNotContainsString('Active', $output);
    }

    public function testRenderShowsStatistics(): void
    {
        $summary = [
            'today' => [
                'sent' => 50,
                'failed' => 5,
                'success_rate' => 90.91
            ],
            'week' => [
                'total' => 375
            ],
            'month' => [
                'total' => 1600,
                'success_rate' => 93.75
            ]
        ];

        $settings = new SmtpSettingsDTO(
            'user@example.com',
            'password',
            'tls',
            'from@example.com',
            'From Name'
        );

        $this->statistics->shouldReceive('getSummary')->andReturn($summary);
        $this->statistics->shouldReceive('getRecentLogs')->andReturn([]);
        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);

        $template = '<?php 
            echo $summary["today"]["sent"] . "|";
            echo $summary["today"]["failed"] . "|";
            echo $summary["today"]["success_rate"] . "|";
            echo $summary["week"]["total"] . "|";
            echo $summary["month"]["total"];
        ?>';
        $this->createMockTemplate($template);

        ob_start();
        $this->widget->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('50', $output);
        $this->assertStringContainsString('5', $output);
        $this->assertStringContainsString('90.91', $output);
        $this->assertStringContainsString('375', $output);
        $this->assertStringContainsString('1600', $output);
    }

    public function testRenderShowsRecentLogs(): void
    {
        $summary = [
            'today' => ['sent' => 50, 'failed' => 5, 'success_rate' => 90.91],
            'week' => ['total' => 375],
            'month' => ['total' => 1600, 'success_rate' => 93.75]
        ];

        $recentLogs = [
            [
                'status' => 'sent',
                'subject' => 'Welcome Email',
                'recipient' => 'user@example.com',
                'created_at' => '2024-01-15 10:30:00'
            ],
            [
                'status' => 'failed',
                'subject' => 'Password Reset',
                'recipient' => 'test@example.com',
                'created_at' => '2024-01-15 10:25:00'
            ]
        ];

        $settings = new SmtpSettingsDTO(
            'user@example.com',
            'password',
            'tls',
            'from@example.com',
            'From Name'
        );

        $this->statistics->shouldReceive('getSummary')->andReturn($summary);
        $this->statistics->shouldReceive('getRecentLogs')->with(5)->andReturn($recentLogs);
        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);

        Functions\expect('current_time')
            ->times(2)
            ->with('timestamp')
            ->andReturn(strtotime('2024-01-15 10:35:00'));

        $template = '<?php foreach($recentLogs as $log): echo $log["subject"] . "|" . $log["recipient"] . "|"; endforeach; ?>';
        $this->createMockTemplate($template);

        ob_start();
        $this->widget->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('Welcome Email', $output);
        $this->assertStringContainsString('Password Reset', $output);
        $this->assertStringContainsString('user@example.com', $output);
        $this->assertStringContainsString('test@example.com', $output);
    }

    public function testRenderShowsNoEmailsMessageWhenNoLogs(): void
    {
        $summary = [
            'today' => ['sent' => 0, 'failed' => 0, 'success_rate' => 0],
            'week' => ['total' => 0],
            'month' => ['total' => 0, 'success_rate' => 0]
        ];

        $settings = new SmtpSettingsDTO(
            'user@example.com',
            'password',
            'tls',
            'from@example.com',
            'From Name'
        );

        $this->statistics->shouldReceive('getSummary')->andReturn($summary);
        $this->statistics->shouldReceive('getRecentLogs')->with(5)->andReturn([]);
        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);

        $template = '<?php if(empty($recentLogs)): echo "No emails sent yet"; endif; ?>';
        $this->createMockTemplate($template);

        ob_start();
        $this->widget->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('No emails sent yet', $output);
    }

    public function testRenderShowsConfigurationMessageWhenNotConfigured(): void
    {
        $summary = [
            'today' => ['sent' => 0, 'failed' => 0, 'success_rate' => 0],
            'week' => ['total' => 0],
            'month' => ['total' => 0, 'success_rate' => 0]
        ];

        $settings = new SmtpSettingsDTO();

        $this->statistics->shouldReceive('getSummary')->andReturn($summary);
        $this->statistics->shouldReceive('getRecentLogs')->with(5)->andReturn([]);
        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);

        $template = '<?php if(!$isConfigured): echo "Please configure SMTP settings first"; endif; ?>';
        $this->createMockTemplate($template);

        ob_start();
        $this->widget->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('Please configure SMTP settings first', $output);
    }

    public function testRenderCallsRequiredMethods(): void
    {
        $summary = ['today' => ['sent' => 0, 'failed' => 0, 'success_rate' => 0], 'week' => ['total' => 0], 'month' => ['total' => 0, 'success_rate' => 0]];
        $settings = new SmtpSettingsDTO();

        $this->statistics->shouldReceive('getSummary')->once()->andReturn($summary);
        $this->statistics->shouldReceive('getRecentLogs')->once()->with(5)->andReturn([]);
        $this->config->shouldReceive('getGeneralSettings')->once()->andReturn($settings);

        $this->createMockTemplate('');

        ob_start();
        $this->widget->render();
        ob_end_clean();
    }

    public function testRenderShowsSettingsLink(): void
    {
        $summary = ['today' => ['sent' => 0, 'failed' => 0, 'success_rate' => 0], 'week' => ['total' => 0], 'month' => ['total' => 0, 'success_rate' => 0]];
        $settings = new SmtpSettingsDTO();

        $this->statistics->shouldReceive('getSummary')->andReturn($summary);
        $this->statistics->shouldReceive('getRecentLogs')->andReturn([]);
        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);

        Functions\expect('admin_url')
            ->once()
            ->with('options-general.php?page=em-smtp-relay-settings')
            ->andReturn('http://example.com/wp-admin/options-general.php?page=em-smtp-relay-settings');

        $template = '<?php echo "View Settings"; ?>';
        $this->createMockTemplate($template);

        ob_start();
        $this->widget->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('View Settings', $output);
    }

    public function testRenderShowsTestEmailLinkWhenConfigured(): void
    {
        $summary = ['today' => ['sent' => 50, 'failed' => 5, 'success_rate' => 90.91], 'week' => ['total' => 375], 'month' => ['total' => 1600, 'success_rate' => 93.75]];
        $settings = new SmtpSettingsDTO('user@example.com', 'password', 'tls', 'from@example.com', 'From Name');

        $this->statistics->shouldReceive('getSummary')->andReturn($summary);
        $this->statistics->shouldReceive('getRecentLogs')->andReturn([]);
        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);

        Functions\expect('admin_url')
            ->times(2)
            ->andReturnUsing(function($arg) {
                return 'http://example.com/wp-admin/' . $arg;
            });

        $template = '<?php if($isConfigured): echo "Send Test Email"; endif; ?>';
        $this->createMockTemplate($template);

        ob_start();
        $this->widget->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('Send Test Email', $output);
    }

    public function testRenderDoesNotShowTestEmailLinkWhenNotConfigured(): void
    {
        $summary = ['today' => ['sent' => 0, 'failed' => 0, 'success_rate' => 0], 'week' => ['total' => 0], 'month' => ['total' => 0, 'success_rate' => 0]];
        $settings = new SmtpSettingsDTO();

        $this->statistics->shouldReceive('getSummary')->andReturn($summary);
        $this->statistics->shouldReceive('getRecentLogs')->andReturn([]);
        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);

        Functions\expect('admin_url')->once()->andReturn('http://example.com/wp-admin/');

        $template = '<?php if(!$isConfigured): echo "Not configured"; else: echo "Send Test Email"; endif; ?>';
        $this->createMockTemplate($template);

        ob_start();
        $this->widget->render();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('Send Test Email', $output);
    }

    public function testEnqueueStylesIncludesAllNecessaryCss(): void
    {
        $screen = (object)['id' => 'dashboard'];

        Functions\expect('get_current_screen')->andReturn($screen);

        $capturedCss = null;
        Functions\expect('wp_add_inline_style')
            ->once()
            ->with('dashboard', Mockery::on(function($css) use (&$capturedCss) {
                $capturedCss = $css;
                return true;
            }));

        $this->widget->enqueueStyles();

        $this->assertStringContainsString('.em-smtp-widget', $capturedCss);
        $this->assertStringContainsString('.em-smtp-status', $capturedCss);
        $this->assertStringContainsString('.em-smtp-stats', $capturedCss);
        $this->assertStringContainsString('.em-smtp-log-item', $capturedCss);
    }

    public function testRenderHandlesPartiallyConfiguredSettings(): void
    {
        $summary = ['today' => ['sent' => 0, 'failed' => 0, 'success_rate' => 0], 'week' => ['total' => 0], 'month' => ['total' => 0, 'success_rate' => 0]];

        // Username and password but no from email
        $settings = new SmtpSettingsDTO('user@example.com', 'password', 'tls', '', '');

        $this->statistics->shouldReceive('getSummary')->andReturn($summary);
        $this->statistics->shouldReceive('getRecentLogs')->andReturn([]);
        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);

        $template = '<?php echo $isConfigured ? "Configured" : "Not Configured"; ?>';
        $this->createMockTemplate($template);

        ob_start();
        $this->widget->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('Not Configured', $output);
    }

    public function testRenderDisplaysLogStatusCorrectly(): void
    {
        $summary = ['today' => ['sent' => 50, 'failed' => 5, 'success_rate' => 90.91], 'week' => ['total' => 375], 'month' => ['total' => 1600, 'success_rate' => 93.75]];
        $recentLogs = [['status' => 'sent', 'subject' => 'Test', 'recipient' => 'test@example.com', 'created_at' => '2024-01-15 10:30:00']];
        $settings = new SmtpSettingsDTO('user@example.com', 'password', 'tls', 'from@example.com', 'From Name');

        $this->statistics->shouldReceive('getSummary')->andReturn($summary);
        $this->statistics->shouldReceive('getRecentLogs')->andReturn($recentLogs);
        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);

        Functions\expect('current_time')->andReturn(strtotime('2024-01-15 10:35:00'));

        $template = '<?php foreach($recentLogs as $log): ?><span class="em-smtp-log-status <?php echo $log["status"]; ?>"></span><?php endforeach; ?>';
        $this->createMockTemplate($template);

        ob_start();
        $this->widget->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('em-smtp-log-status sent', $output);
    }
}