<?php

declare(strict_types=1);

namespace Emercury\Smtp\Admin;

use Emercury\Smtp\Contracts\EmailStatisticsInterface;
use Emercury\Smtp\Contracts\ConfigInterface;
use Emercury\Smtp\Contracts\LocalizationInterface;

class DashboardWidget
{
    private EmailStatisticsInterface $statistics;
    private ConfigInterface $config;
    private LocalizationInterface $localization;

    public function __construct(
        EmailStatisticsInterface $statistics,
        LocalizationInterface $localization,
        ConfigInterface $config
    ) {
        $this->statistics = $statistics;
        $this->config = $config;
        $this->localization = $localization;
    }

    public function register(): void
    {
        add_action('wp_dashboard_setup', [$this, 'addWidget']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueStyles']);
    }

    public function addWidget(): void
    {
        wp_add_dashboard_widget(
            'em_smtp_dashboard_widget',
            $this->localization->t('Emercury SMTP Status'),
            [$this, 'render'],
            null,
            null,
            'side',
            'high'
        );
    }

    public function enqueueStyles(): void
    {
        $screen = get_current_screen();

        if ($screen && $screen->id === 'dashboard') {
            wp_add_inline_style('dashboard', $this->getInlineStyles());
        }
    }

    public function render(): void
    {
        $summary = $this->statistics->getSummary();
        $recentLogs = $this->statistics->getRecentLogs(5);
        $settings = $this->config->getGeneralSettings();

        $isConfigured = !empty($settings->smtpUsername)
            && !empty($settings->smtpPassword)
            && !empty($settings->fromEmail);

        $l10n = $this->localization;

        include EM_SMTP_PATH . 'templates/admin/dashboard-widget.php';
    }

    private function getInlineStyles(): string
    {
        return '
            .em-smtp-widget {
                font-size: 13px;
            }
            .em-smtp-widget-header {
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 1px solid #dcdcde;
            }
            .em-smtp-status {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
            }
            .em-smtp-status.active {
                background: #00a32a;
                color: #fff;
            }
            .em-smtp-status.inactive {
                background: #dba617;
                color: #fff;
            }
            .em-smtp-stats {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 10px;
                margin-bottom: 15px;
            }
            .em-smtp-stat-box {
                padding: 12px;
                background: #f6f7f7;
                border-radius: 4px;
                text-align: center;
            }
            .em-smtp-stat-value {
                display: block;
                font-size: 24px;
                font-weight: 600;
                color: #1d2327;
                line-height: 1.2;
            }
            .em-smtp-stat-label {
                display: block;
                font-size: 11px;
                color: #646970;
                margin-top: 4px;
                text-transform: uppercase;
            }
            .em-smtp-stat-box.success .em-smtp-stat-value {
                color: #00a32a;
            }
            .em-smtp-stat-box.error .em-smtp-stat-value {
                color: #d63638;
            }
            .em-smtp-recent-logs {
                margin-top: 15px;
            }
            .em-smtp-recent-logs h4 {
                margin: 0 0 10px 0;
                font-size: 13px;
                font-weight: 600;
            }
            .em-smtp-log-item {
                padding: 8px 0;
                border-bottom: 1px solid #f0f0f1;
                font-size: 12px;
            }
            .em-smtp-log-item:last-child {
                border-bottom: none;
            }
            .em-smtp-log-status {
                display: inline-block;
                width: 8px;
                height: 8px;
                border-radius: 50%;
                margin-right: 6px;
            }
            .em-smtp-log-status.sent {
                background: #00a32a;
            }
            .em-smtp-log-status.failed {
                background: #d63638;
            }
            .em-smtp-log-subject {
                color: #1d2327;
                font-weight: 500;
            }
            .em-smtp-log-time {
                color: #646970;
                font-size: 11px;
            }
            .em-smtp-widget-footer {
                margin-top: 15px;
                padding-top: 10px;
                border-top: 1px solid #dcdcde;
                text-align: center;
            }
            .em-smtp-widget-footer a {
                text-decoration: none;
            }
            .em-smtp-no-data {
                padding: 20px;
                text-align: center;
                color: #646970;
                background: #f6f7f7;
                border-radius: 4px;
            }
        ';
    }
}