<?php

declare(strict_types=1);

namespace Emercury\Smtp\Admin\Tabs;

use Emercury\Smtp\Admin\AdminNotifier;
use Emercury\Smtp\App\Localization;
use Emercury\Smtp\Contracts\ConfigInterface;
use Emercury\Smtp\Contracts\NonceManagerInterface;

class ConfigManagerTab
{
    private ConfigInterface $config;
    private NonceManagerInterface $nonceManager;
    private AdminNotifier $notifier;
    private Localization $localization;

    public function __construct(
        ConfigInterface $config,
        NonceManagerInterface $nonceManager,
        Localization $localization,
        AdminNotifier $notifier,
    ) {
        $this->config = $config;
        $this->nonceManager = $nonceManager;
        $this->notifier = $notifier;
        $this->localization = $localization;
        $this->init();
    }

    public function init(): void
    {
        add_action('admin_init', function () {
            if (isset($_POST['em_smtp_relay_export_config'])) {
                $this->handleExport();
            }

            if (isset($_POST['em_smtp_relay_import_config'])) {
                $this->handleImport();
            }
        });
    }

    public function render(): void
    {
        include EM_SMTP_PATH . 'templates/admin/config-manager-tab.php';
    }

    private function handleExport(): void
    {
        if (!$this->nonceManager->verifyWithCapability('em_smtp_relay_export_config')) {
            wp_die(
                $this->localization->escHtml('Security check failed. Please try again.'),
                $this->localization->escHtml('Security Error'),
                ['response' => 403]
            );
        }

        $generalSettings = $this->config->getGeneralSettings();
        $advancedSettings = $this->config->getAdvancedSettings();

        if ($generalSettings->smtpPort === 0) {
            $generalSettings->smtpPort = $this->config->getSmtpPort($generalSettings->smtpEncryption);
        }

        $generalArray = $generalSettings->toArray();
        $advancedArray = $advancedSettings->toArray();

        $exportData = [
            'version' => EM_SMTP_VERSION,
            'export_date' => current_time('mysql'),
            'general' => $generalArray,
            'advanced' => $advancedArray,
        ];

        unset($exportData['general']['em_smtp_password']);

        $filename = 'emercury-smtp-config.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo wp_json_encode($exportData, JSON_PRETTY_PRINT);
        exit;
    }

    private function handleImport(): void
    {
        if (!$this->nonceManager->verifyWithCapability('em_smtp_relay_import_config')) {
            wp_die(
                $this->localization->escHtml('Security check failed. Please try again.'),
                $this->localization->escHtml('Security Error'),
                ['response' => 403]
            );
        }

        if (!isset($_FILES['config_file']) || $_FILES['config_file']['error'] !== UPLOAD_ERR_OK) {
            $this->notifier->addError($this->localization->t('Please select a valid configuration file.'));
            return;
        }

        $file = $_FILES['config_file'];

        if ($file['type'] !== 'application/json' && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'json') {
            $this->notifier->addError($this->localization->t('Invalid file type. Please upload a JSON file.'));
            return;
        }

        if ($file['size'] > 1048576) {
            $this->notifier->addError($this->localization->t('File is too large. Maximum size is 1MB.'));
            return;
        }

        $content = file_get_contents($file['tmp_name']);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->notifier->addError($this->localization->t('Invalid JSON format.'));
            return;
        }

        if (!isset($data['version']) || !isset($data['general']) || !isset($data['advanced'])) {
            $this->notifier->addError($this->localization->t('Invalid configuration file structure.'));
            return;
        }

        $overwritePassword = isset($_POST['overwrite_password']) && $_POST['overwrite_password'] === '1';

        try {
            if (!$overwritePassword) {
                $currentSettings = $this->config->getGeneralSettings();
                $data['general']['em_smtp_password'] = $currentSettings->smtpPassword;
            }

            update_option('em_smtp_relay_data', $data['general']);
            update_option('em_smtp_relay_advanced_data', $data['advanced']);

            $this->notifier->addSuccess($this->localization->t('Configuration imported successfully!'));
        } catch (\Exception $e) {
            $this->notifier->addError($this->localization->t('Failed to import configuration: ') . $e->getMessage());
        }
    }
}
