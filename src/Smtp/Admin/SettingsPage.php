<?php

declare(strict_types=1);

namespace Emercury\Smtp\Admin;

use Emercury\Smtp\Admin\Tabs\AdvancedTab;
use Emercury\Smtp\Admin\Tabs\ConfigManagerTab;
use Emercury\Smtp\Admin\Tabs\GeneralTab;
use Emercury\Smtp\Admin\Tabs\TestEmailTab;
use Emercury\Smtp\App\Localization;

class SettingsPage
{
    private GeneralTab $generalTab;
    private AdvancedTab $advancedTab;
    private TestEmailTab $testEmailTab;
    private ConfigManagerTab $configManagerTab;
    private Localization $localization;

    public function __construct(
        GeneralTab $generalTab,
        AdvancedTab $advancedTab,
        TestEmailTab $testEmailTab,
        Localization $localization,
        ConfigManagerTab $configManagerTab
    ) {
        $this->generalTab = $generalTab;
        $this->advancedTab = $advancedTab;
        $this->testEmailTab = $testEmailTab;
        $this->configManagerTab = $configManagerTab;
        $this->localization = $localization;
    }

    public function registerMenu(): void
    {
        add_options_page(
            $this->localization->t('Emercury SMTP'),
            $this->localization->t('Emercury SMTP'),
            'manage_options',
            'em-smtp-relay-settings',
            [$this, 'render']
        );
    }

    /**
     * @param array<mixed> $links
     */
    public function addActionLinks(array $links): array
    {
        $settingsLink = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('options-general.php?page=em-smtp-relay-settings')),
            $this->localization->escHtml('Settings')
        );

        array_unshift($links, $settingsLink);

        return $links;
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                $this->localization->escHtml('You do not have sufficient permissions to access this page.'),
                $this->localization->escHtml('Permission Denied'),
                ['response' => 403]
            );
        }

        echo '<div class="wrap">';
        echo '<h1>' . $this->localization->escHtml('Emercury SMTP Settings') . '</h1>';
        echo '<p>' . wp_kses_post(sprintf(
            $this->localization->t('%s <a href="%s" target="_blank" rel="noopener">%s</a> %s'),
            'Log into your',
            'https://panel.smtp.emercury.net/',
            'Emercury SMTP account',
            'to obtain the settings.'
        )) . '</p>';

        $this->renderTabs();
        $this->renderActiveTab();

        echo '</div>';
    }

    private function renderTabs(): void
    {
        $tabs = [
            'general' => $this->localization->t('Settings'),
            'test-email' => $this->localization->t('Test Email'),
            'advanced' => $this->localization->t('Advanced'),
            'config-manager' => $this->localization->t('Import/Export'),
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
                'tab' => $tab,
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

            case 'config-manager':
                $this->configManagerTab->render();
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
