<?php

declare(strict_types=1);

namespace Emercury\Smtp\Admin;

use Emercury\Smtp\App\Localization;

class StatisticsPage
{
    private AdvancedStatistics $statistics;
    private Localization $localization;

    public function __construct(AdvancedStatistics $statistics, Localization $localization)
    {
        $this->statistics = $statistics;
        $this->localization = $localization;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'options-general.php',
            $this->localization->t('Email Statistics'),
            $this->localization->t('Email Stats'),
            'manage_options',
            'em-smtp-statistics',
            [$this, 'render']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'settings_page_em-smtp-statistics') {
            return;
        }

        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            [],
            '4.4.0',
            true
        );

        wp_enqueue_script(
            'em-smtp-statistics',
            EM_SMTP_URL . 'assets/js/statistics.js',
            ['chart-js', 'jquery'],
            EM_SMTP_VERSION,
            true
        );

        wp_localize_script('em-smtp-statistics', 'emSmtpStats', [
            'dailyData' => $this->statistics->getDailyChartData(),
            'hourlyData' => $this->statistics->getChartData(),
        ]);

        wp_enqueue_style(
            'em-smtp-statistics',
            EM_SMTP_URL . 'assets/css/statistics.css',
            [],
            EM_SMTP_VERSION
        );
    }

    public function render(): void
    {
        $metrics = $this->statistics->getKeyMetrics();
        include EM_SMTP_PATH . 'templates/admin/statistics-page.php';
    }
}
