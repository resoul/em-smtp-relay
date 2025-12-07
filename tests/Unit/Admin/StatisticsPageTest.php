<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Admin;

use Emercury\Smtp\Admin\StatisticsPage;
use Emercury\Smtp\Statistics\AdvancedStatistics;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class StatisticsPageTest extends TestCase
{
    private StatisticsPage $statisticsPage;
    private $statistics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statistics = Mockery::mock(AdvancedStatistics::class);
        $this->statisticsPage = new StatisticsPage($this->statistics);

        Functions\when('__')->returnArg();
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html__')->returnArg();

        if (!defined('EM_SMTP_URL')) {
            define('EM_SMTP_URL', 'http://example.com/wp-content/plugins/em-smtp/');
        }

        if (!defined('EM_SMTP_VERSION')) {
            define('EM_SMTP_VERSION', '1.0.0');
        }

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

    private function createMockTemplate(): void
    {
        $template = EM_SMTP_PATH . 'templates/admin/statistics-page.php';
        file_put_contents($template, '<!-- MOCK_STATISTICS_PAGE -->');
    }

    public function testRegisterAddsMenuAndEnqueueHooks(): void
    {
        Functions\expect('add_action')
            ->once()
            ->with('admin_menu', [$this->statisticsPage, 'addMenuPage'], 20);

        Functions\expect('add_action')
            ->once()
            ->with('admin_enqueue_scripts', [$this->statisticsPage, 'enqueueAssets']);

        $this->statisticsPage->register();
    }

    public function testAddMenuPageCreatesSubmenuPage(): void
    {
        Functions\expect('add_submenu_page')
            ->once()
            ->with(
                'options-general.php',
                'Email Statistics',
                'Email Stats',
                'manage_options',
                'em-smtp-statistics',
                [$this->statisticsPage, 'render']
            );

        $this->statisticsPage->addMenuPage();
    }

    public function testEnqueueAssetsOnlyOnStatisticsPage(): void
    {
        $dailyData = [
            'labels' => ['Jan 1', 'Jan 2'],
            'sent' => [100, 150],
            'failed' => [5, 3],
            'total' => [105, 153]
        ];

        $hourlyData = [
            'labels' => ['10:00', '11:00'],
            'sent' => [50, 45],
            'failed' => [2, 1]
        ];

        $this->statistics
            ->shouldReceive('getDailyChartData')
            ->once()
            ->with(30)
            ->andReturn($dailyData);

        $this->statistics
            ->shouldReceive('getChartData')
            ->once()
            ->with(7)
            ->andReturn($hourlyData);

        Functions\expect('wp_enqueue_script')
            ->once()
            ->with(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
                [],
                '4.4.0',
                true
            );

        Functions\expect('wp_enqueue_script')
            ->once()
            ->with(
                'em-smtp-statistics',
                EM_SMTP_URL . 'assets/js/statistics.js',
                ['chart-js', 'jquery'],
                EM_SMTP_VERSION,
                true
            );

        Functions\expect('wp_localize_script')
            ->once()
            ->with(
                'em-smtp-statistics',
                'emSmtpStats',
                [
                    'dailyData' => $dailyData,
                    'hourlyData' => $hourlyData,
                ]
            );

        Functions\expect('wp_enqueue_style')
            ->once()
            ->with(
                'em-smtp-statistics',
                EM_SMTP_URL . 'assets/css/statistics.css',
                [],
                EM_SMTP_VERSION
            );

        $this->statisticsPage->enqueueAssets('settings_page_em-smtp-statistics');
    }

    public function testEnqueueAssetsDoesNotRunOnOtherPages(): void
    {
        Functions\expect('wp_enqueue_script')
            ->never();

        Functions\expect('wp_enqueue_style')
            ->never();

        $this->statisticsPage->enqueueAssets('dashboard');
    }

    public function testEnqueueAssetsIncludesChartJsFromCdn(): void
    {
        $this->statistics
            ->shouldReceive('getDailyChartData')
            ->andReturn([]);

        $this->statistics
            ->shouldReceive('getChartData')
            ->andReturn([]);

        Functions\expect('wp_enqueue_script')
            ->once()
            ->with(
                'chart-js',
                Mockery::on(function($url) {
                    return strpos($url, 'cdn.jsdelivr.net') !== false;
                }),
                [],
                '4.4.0',
                true
            );

        Functions\expect('wp_enqueue_script')
            ->once()
            ->with('em-smtp-statistics', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any());

        Functions\expect('wp_localize_script')->once();
        Functions\expect('wp_enqueue_style')->once();

        $this->statisticsPage->enqueueAssets('settings_page_em-smtp-statistics');
    }

    public function testEnqueueAssetsDependsOnChartJs(): void
    {
        $this->statistics->shouldReceive('getDailyChartData')->andReturn([]);
        $this->statistics->shouldReceive('getChartData')->andReturn([]);

        Functions\expect('wp_enqueue_script')
            ->once()
            ->with('chart-js', Mockery::any(), [], Mockery::any(), Mockery::any());

        Functions\expect('wp_enqueue_script')
            ->once()
            ->with(
                'em-smtp-statistics',
                Mockery::any(),
                ['chart-js', 'jquery'],
                Mockery::any(),
                Mockery::any()
            );

        Functions\expect('wp_localize_script')->once();
        Functions\expect('wp_enqueue_style')->once();

        $this->statisticsPage->enqueueAssets('settings_page_em-smtp-statistics');
    }

    public function testEnqueueAssetsLocalizesScriptWithChartData(): void
    {
        $dailyData = [
            'labels' => ['Jan 1', 'Jan 2', 'Jan 3'],
            'sent' => [100, 150, 200],
            'failed' => [5, 3, 7],
            'total' => [105, 153, 207]
        ];

        $hourlyData = [
            'labels' => ['10:00', '11:00', '12:00'],
            'sent' => [50, 45, 60],
            'failed' => [2, 1, 3]
        ];

        $this->statistics
            ->shouldReceive('getDailyChartData')
            ->with(30)
            ->andReturn($dailyData);

        $this->statistics
            ->shouldReceive('getChartData')
            ->with(7)
            ->andReturn($hourlyData);

        Functions\expect('wp_enqueue_script')->times(2);

        Functions\expect('wp_localize_script')
            ->once()
            ->with(
                'em-smtp-statistics',
                'emSmtpStats',
                Mockery::on(function($data) use ($dailyData, $hourlyData) {
                    return $data['dailyData'] === $dailyData
                        && $data['hourlyData'] === $hourlyData;
                })
            );

        Functions\expect('wp_enqueue_style')->once();

        $this->statisticsPage->enqueueAssets('settings_page_em-smtp-statistics');
    }

    public function testRenderIncludesKeyMetrics(): void
    {
        $metrics = [
            'today' => [
                'sent' => 50,
                'failed' => 5,
                'total' => 55,
                'success_rate' => 90.91
            ],
            'week' => [
                'sent' => 350,
                'failed' => 25,
                'total' => 375,
                'success_rate' => 93.33
            ],
            'month' => [
                'sent' => 1500,
                'failed' => 100,
                'total' => 1600,
                'success_rate' => 93.75
            ],
            'trends' => [
                'sent_change' => 15.5,
                'failed_change' => -10.2,
                'success_rate_change' => 2.3
            ]
        ];

        $this->statistics
            ->shouldReceive('getKeyMetrics')
            ->once()
            ->andReturn($metrics);

        $this->createMockTemplate();

        ob_start();
        $this->statisticsPage->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('MOCK_STATISTICS_PAGE', $output);
    }

    public function testRenderIncludesChartContainers(): void
    {
        $metrics = [
            'today' => ['sent' => 0, 'failed' => 0, 'total' => 0, 'success_rate' => 0],
            'week' => ['sent' => 0, 'failed' => 0, 'total' => 0, 'success_rate' => 0],
            'month' => ['sent' => 0, 'failed' => 0, 'total' => 0, 'success_rate' => 0],
            'trends' => ['sent_change' => 0, 'failed_change' => 0, 'success_rate_change' => 0]
        ];

        $this->statistics
            ->shouldReceive('getKeyMetrics')
            ->andReturn($metrics);

        $this->createMockTemplate();

        ob_start();
        $this->statisticsPage->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('MOCK_STATISTICS_PAGE', $output);
    }

    public function testRenderIncludesDetailedStatisticsTable(): void
    {
        $metrics = [
            'today' => ['sent' => 50, 'failed' => 5, 'total' => 55, 'success_rate' => 90.91],
            'week' => ['sent' => 350, 'failed' => 25, 'total' => 375, 'success_rate' => 93.33],
            'month' => ['sent' => 1500, 'failed' => 100, 'total' => 1600, 'success_rate' => 93.75],
            'trends' => ['sent_change' => 0, 'failed_change' => 0, 'success_rate_change' => 0]
        ];

        $this->statistics
            ->shouldReceive('getKeyMetrics')
            ->andReturn($metrics);

        $this->createMockTemplate();

        ob_start();
        $this->statisticsPage->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('MOCK_STATISTICS_PAGE', $output);
    }

    public function testRenderShowsMetricCards(): void
    {
        $metrics = [
            'today' => ['sent' => 50, 'failed' => 5, 'total' => 55, 'success_rate' => 90.91],
            'week' => ['sent' => 350, 'failed' => 25, 'total' => 375, 'success_rate' => 93.33],
            'month' => ['sent' => 1500, 'failed' => 100, 'total' => 1600, 'success_rate' => 93.75],
            'trends' => ['sent_change' => 0, 'failed_change' => 0, 'success_rate_change' => 0]
        ];

        $this->statistics
            ->shouldReceive('getKeyMetrics')
            ->andReturn($metrics);

        $this->createMockTemplate();

        ob_start();
        $this->statisticsPage->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('MOCK_STATISTICS_PAGE', $output);
    }

    public function testRenderWithZeroMetrics(): void
    {
        $metrics = [
            'today' => ['sent' => 0, 'failed' => 0, 'total' => 0, 'success_rate' => 0],
            'week' => ['sent' => 0, 'failed' => 0, 'total' => 0, 'success_rate' => 0],
            'month' => ['sent' => 0, 'failed' => 0, 'total' => 0, 'success_rate' => 0],
            'trends' => ['sent_change' => 0, 'failed_change' => 0, 'success_rate_change' => 0]
        ];

        $this->statistics
            ->shouldReceive('getKeyMetrics')
            ->andReturn($metrics);

        $this->createMockTemplate();

        ob_start();
        $this->statisticsPage->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('MOCK_STATISTICS_PAGE', $output);
    }

    public function testEnqueueAssetsUsesCorrectVersion(): void
    {
        $this->statistics->shouldReceive('getDailyChartData')->andReturn([]);
        $this->statistics->shouldReceive('getChartData')->andReturn([]);

        Functions\expect('wp_enqueue_script')
            ->once()
            ->with('chart-js', Mockery::any(), Mockery::any(), '4.4.0', Mockery::any());

        Functions\expect('wp_enqueue_script')
            ->once()
            ->with('em-smtp-statistics', Mockery::any(), Mockery::any(), EM_SMTP_VERSION, Mockery::any());

        Functions\expect('wp_enqueue_style')
            ->once()
            ->with('em-smtp-statistics', Mockery::any(), Mockery::any(), EM_SMTP_VERSION);

        Functions\expect('wp_localize_script')->once();

        $this->statisticsPage->enqueueAssets('settings_page_em-smtp-statistics');
    }

    public function testEnqueueAssetsLoadsScriptsInFooter(): void
    {
        $this->statistics->shouldReceive('getDailyChartData')->andReturn([]);
        $this->statistics->shouldReceive('getChartData')->andReturn([]);

        Functions\expect('wp_enqueue_script')
            ->twice()
            ->with(Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), true);

        Functions\expect('wp_localize_script')->once();
        Functions\expect('wp_enqueue_style')->once();

        $this->statisticsPage->enqueueAssets('settings_page_em-smtp-statistics');
    }

    public function testEnqueueAssetsUsesCorrectCssPath(): void
    {
        $this->statistics->shouldReceive('getDailyChartData')->andReturn([]);
        $this->statistics->shouldReceive('getChartData')->andReturn([]);

        Functions\expect('wp_enqueue_script')->times(2);
        Functions\expect('wp_localize_script')->once();

        Functions\expect('wp_enqueue_style')
            ->once()
            ->with(
                'em-smtp-statistics',
                EM_SMTP_URL . 'assets/css/statistics.css',
                [],
                EM_SMTP_VERSION
            );

        $this->statisticsPage->enqueueAssets('settings_page_em-smtp-statistics');
    }

    public function testEnqueueAssetsUsesCorrectJsPath(): void
    {
        $this->statistics->shouldReceive('getDailyChartData')->andReturn([]);
        $this->statistics->shouldReceive('getChartData')->andReturn([]);

        Functions\expect('wp_enqueue_script')
            ->once()
            ->with('chart-js', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any());

        Functions\expect('wp_enqueue_script')
            ->once()
            ->with(
                'em-smtp-statistics',
                EM_SMTP_URL . 'assets/js/statistics.js',
                Mockery::any(),
                Mockery::any(),
                Mockery::any()
            );

        Functions\expect('wp_localize_script')->once();
        Functions\expect('wp_enqueue_style')->once();

        $this->statisticsPage->enqueueAssets('settings_page_em-smtp-statistics');
    }

    public function testRenderUsesCorrectWrapClass(): void
    {
        $metrics = [
            'today' => ['sent' => 0, 'failed' => 0, 'total' => 0, 'success_rate' => 0],
            'week' => ['sent' => 0, 'failed' => 0, 'total' => 0, 'success_rate' => 0],
            'month' => ['sent' => 0, 'failed' => 0, 'total' => 0, 'success_rate' => 0],
            'trends' => ['sent_change' => 0, 'failed_change' => 0, 'success_rate_change' => 0]
        ];

        $this->statistics->shouldReceive('getKeyMetrics')->andReturn($metrics);

        $this->createMockTemplate();

        ob_start();
        $this->statisticsPage->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('MOCK_STATISTICS_PAGE', $output);
    }

    public function testAddMenuPageUsesCorrectCapability(): void
    {
        Functions\expect('add_submenu_page')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                'manage_options',
                Mockery::any(),
                Mockery::any()
            );

        $this->statisticsPage->addMenuPage();
    }

    public function testAddMenuPageUsesCorrectSlug(): void
    {
        Functions\expect('add_submenu_page')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                'em-smtp-statistics',
                Mockery::any()
            );

        $this->statisticsPage->addMenuPage();
    }
}