<?php

declare(strict_types=1);

namespace Emercury\Smtp\Core;

use Emercury\Smtp\Admin\DashboardWidget;
use Emercury\Smtp\Admin\SettingsPage;
use Emercury\Smtp\Contracts\EmailLoggerInterface;

class Plugin
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function init(): void
    {
        $this->loadTextDomain();
        $this->registerHooks();
        $this->scheduleCleanup();
    }

    private function loadTextDomain(): void
    {
        add_action('plugins_loaded', function () {
            load_plugin_textdomain(
                'em-smtp-relay',
                false,
                dirname(EM_SMTP_BASENAME) . '/languages'
            );
        });
    }

    private function registerHooks(): void
    {
        // Initialize mailer
        $mailer = $this->container->get(Mailer::class);
        add_filter('pre_wp_mail', [$mailer, 'sendMail'], 10, 2);

        // Admin hooks
        if (is_admin()) {
            $this->registerAdminHooks();
        }
    }

    private function registerAdminHooks(): void
    {
        $settingsPage = $this->container->get(SettingsPage::class);

        add_action('admin_menu', [$settingsPage, 'registerMenu']);
        add_filter('plugin_action_links_' . EM_SMTP_BASENAME, [
            $settingsPage,
            'addActionLinks'
        ]);

        $dashboardWidget = $this->container->get(DashboardWidget::class);
        $dashboardWidget->register();
    }

    private function scheduleCleanup(): void
    {
        if (!wp_next_scheduled('em_smtp_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'em_smtp_cleanup_logs');
        }

        add_action('em_smtp_cleanup_logs', function() {
            $emailLogger = $this->container->get(EmailLoggerInterface::class);
            $emailLogger->clearOldLogs(30);
        });
    }
}