<?php

declare(strict_types=1);

namespace Emercury\Smtp\Admin;

use Emercury\Smtp\Admin\Tabs\GeneralTab;
use Emercury\Smtp\Admin\Tabs\AdvancedTab;
use Emercury\Smtp\Admin\Tabs\TestEmailTab;

class SettingsPage
{
    private GeneralTab $generalTab;
    private AdvancedTab $advancedTab;
    private TestEmailTab $testEmailTab;

    public function __construct(
        GeneralTab $generalTab,
        AdvancedTab $advancedTab,
        TestEmailTab $testEmailTab
    ) {
        $this->generalTab = $generalTab;
        $this->advancedTab = $advancedTab;
        $this->testEmailTab = $testEmailTab;
    }

    public function registerMenu(): void
    {
        add_options_page(
            __('Emercury SMTP', 'em-smtp-relay'),
            __('Emercury SMTP', 'em-smtp-relay'),
            'manage_options',
            'em-smtp-relay-settings',
            [$this, 'render']
        );
    }

    public function addActionLinks(array $links): array
    {
        $settingsLink = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('options-general.php?page=em-smtp-relay-settings')),
            esc_html__('Settings', 'em-smtp-relay')
        );

        array_unshift($links, $settingsLink);

        return $links;
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have sufficient permissions to access this page.', 'em-smtp-relay'),
                esc_html__('Permission Denied', 'em-smtp-relay'),
                ['response' => 403]
            );
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Emercury SMTP Settings', 'em-smtp-relay') . '</h1>';
        echo '<p>' . wp_kses_post(sprintf(
                __('Log into your <a href="%s" target="_blank" rel="noopener">Emercury SMTP account</a> to obtain the settings.', 'em-smtp-relay'),
                'https://panel.smtp.emercury.net/'
            )) . '</p>';

        $this->renderTabs();
        $this->renderActiveTab();

        echo '</div>';
    }

    private function renderTabs(): void
    {
        $tabs = [
            'general' => __('Settings', 'em-smtp-relay'),
            'test-email' => __('Test Email', 'em-smtp-relay'),
            'advanced' => __('Advanced', 'em-smtp-relay'),
        ];

        $currentTab = $this->getCurrentTab();

        echo '<h2 class="nav-tab-wrapper">';

        foreach ($tabs as $tab => $label) {
            $class = 'nav-tab';
            if ($currentTab === $tab) {
                $class .= ' nav-tab-active';
            }

            $url = add_query_arg([
                'page' => 'em-smtp-relay-settings',
                'tab' => $tab
            ], admin_url('options-general.php'));

            printf(
                '<a class="%s" href="%s">%s</a>',
                esc_attr($class),
                esc_url($url),
                esc_html($label)
            );
        }

        echo '</h2>';
    }

    private function renderActiveTab(): void
    {
        $tab = $this->getCurrentTab();

        switch ($tab) {
            case 'test-email':
                $this->testEmailTab->render();
                break;
            case 'advanced':
                $this->advancedTab->render();
                break;
            default:
                $this->generalTab->render();
                break;
        }
    }

    private function getCurrentTab(): string
    {
        return sanitize_text_field($_GET['tab'] ?? 'general');
    }
}