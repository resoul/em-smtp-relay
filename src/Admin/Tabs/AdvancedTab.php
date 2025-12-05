<?php

declare(strict_types=1);

namespace Emercury\Smtp\Admin\Tabs;

use Emercury\Smtp\Security\Validator;
use Emercury\Smtp\Security\NonceManager;
use Emercury\Smtp\Config\Config;

class AdvancedTab
{
    private Validator $validator;
    private NonceManager $nonceManager;
    private Config $config;

    public function __construct(
        Validator $validator,
        NonceManager $nonceManager,
        Config $config
    ) {
        $this->validator = $validator;
        $this->nonceManager = $nonceManager;
        $this->config = $config;
    }

    public function render(): void
    {
        if (isset($_POST['em_smtp_relay_update_advanced_settings'])) {
            $this->handleFormSubmission();
        }

        $data = $this->config->getAdvancedSettings();

        include EM_SMTP_PATH . 'templates/advanced-tab.php';
    }

    private function handleFormSubmission(): void
    {
        if (!$this->nonceManager->verifyWithCapability('em_smtp_relay_advanced_settings')) {
            wp_die(
                esc_html__('Security check failed. Please try again.', 'em-smtp-relay'),
                esc_html__('Security Error', 'em-smtp-relay'),
                ['response' => 403]
            );
        }

        $rawData = [
            'reply_to_email' => $_POST['reply_to_email'] ?? '',
            'reply_to_name' => $_POST['reply_to_name'] ?? '',
            'force_reply_to' => $_POST['force_reply_to'] ?? '',
            'cc_email' => $_POST['cc_email'] ?? '',
            'cc_name' => $_POST['cc_name'] ?? '',
            'force_cc' => $_POST['force_cc'] ?? '',
            'bcc_email' => $_POST['bcc_email'] ?? '',
            'bcc_name' => $_POST['bcc_name'] ?? '',
            'force_bcc' => $_POST['force_bcc'] ?? '',
        ];

        $data = $this->validator->sanitizeAdvancedSettings($rawData);
        $errors = $this->validator->validateAdvancedSettings($rawData);

        if (!empty($errors)) {
            $this->displayErrors($errors);
            return;
        }

        $saveData = [
            'em_smtp_reply_to_email' => $data['reply_to_email'],
            'em_smtp_reply_to_name' => $data['reply_to_name'],
            'em_smtp_force_reply_to' => $data['force_reply_to'],
            'em_smtp_cc_email' => $data['cc_email'],
            'em_smtp_cc_name' => $data['cc_name'],
            'em_smtp_force_cc' => $data['force_cc'],
            'em_smtp_bcc_email' => $data['bcc_email'],
            'em_smtp_bcc_name' => $data['bcc_name'],
            'em_smtp_force_bcc' => $data['force_bcc'],
        ];

        $this->config->saveAdvancedSettings($saveData);

        $this->displaySuccess(__('Settings Saved!', 'em-smtp-relay'));
    }

    private function displaySuccess(string $message): void
    {
        echo ''
            . esc_html($message)
            . '';
    }

    private function displayErrors(array $errors): void
    {
        echo '';
        foreach ($errors as $error) {
            echo '' . esc_html($error) . '';
        }
        echo '';
    }
}