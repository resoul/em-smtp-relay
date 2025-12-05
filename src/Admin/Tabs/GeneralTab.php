<?php

declare(strict_types=1);

namespace Emercury\Smtp\Admin\Tabs;

use Emercury\Smtp\Security\Encryption;
use Emercury\Smtp\Security\Validator;
use Emercury\Smtp\Security\NonceManager;
use Emercury\Smtp\Config\Config;
use Emercury\Smtp\Security\RateLimiter;

class GeneralTab
{
    private Encryption $encryption;
    private Validator $validator;
    private NonceManager $nonceManager;
    private Config $config;
    private RateLimiter $rateLimiter;

    public function __construct(
        Encryption $encryption,
        Validator $validator,
        NonceManager $nonceManager,
        Config $config,
        RateLimiter $rateLimiter
    ) {
        $this->encryption = $encryption;
        $this->validator = $validator;
        $this->nonceManager = $nonceManager;
        $this->config = $config;
        $this->rateLimiter = $rateLimiter;
    }

    public function render(): void
    {
        if (isset($_POST['em_smtp_relay_update_settings'])) {
            $this->handleSubmit();
        }

        $data = $this->config->getGeneralSettings();

        include EM_SMTP_PATH . 'templates/admin/general-tab.php';
    }

    private function handleSubmit(): void
    {
        if (!$this->nonceManager->verifyWithCapability('em_smtp_relay_settings')) {
            wp_die(
                esc_html__('Security check failed. Please try again.', 'em-smtp-relay'),
                esc_html__('Security Error', 'em-smtp-relay'),
                ['response' => 403]
            );
        }

        $userId = get_current_user_id();
        if (!$this->rateLimiter->checkLimit('settings_update_' . $userId)) {
            $this->displayErrors([
                __('Too many update attempts. Please wait before trying again.', 'em-smtp-relay')
            ]);
            return;
        }

        $rawData = [
            'smtp_username' => $_POST['smtp_username'] ?? '',
            'smtp_password' => $_POST['smtp_password'] ?? '',
            'smtp_encryption' => $_POST['encryption'] ?? 'tls',
            'from_email' => $_POST['from_email'] ?? '',
            'from_name' => $_POST['from_name'] ?? '',
            'force_from_address' => $_POST['force_from_address'] ?? '',
        ];

        $sanitized = $this->validator->sanitizeSettings($rawData);
        $errors = $this->validator->validateSmtpSettings($sanitized);

        if (!empty($errors)) {
            $this->displayErrors($errors);
            return;
        }

        $this->saveSettings($sanitized);
    }

    private function saveSettings(array $sanitizedData): void
    {
        $smtpPassword = $this->processPassword($sanitizedData['smtp_password']);

        $data = [
            'em_smtp_host' => Config::SMTP_HOST,
            'em_smtp_auth' => 'true',
            'em_smtp_username' => $sanitizedData['smtp_username'],
            'em_smtp_password' => $smtpPassword,
            'em_smtp_encryption' => $sanitizedData['smtp_encryption'],
            'em_smtp_from_email' => $sanitizedData['from_email'],
            'em_smtp_from_name' => $sanitizedData['from_name'],
            'em_smtp_force_from_address' => $sanitizedData['force_from_address'],
            'em_smtp_port' => $this->config->getSmtpPort($sanitizedData['smtp_encryption']),
        ];

        $this->config->saveGeneralSettings($data);

        $this->displaySuccess(__('Settings Saved!', 'em-smtp-relay'));
    }

    private function processPassword(string $password): string
    {
        if (!empty($password)) {
            try {
                return $this->encryption->encrypt($password);
            } catch (\Exception $e) {
                wp_die(
                    esc_html__('Failed to encrypt password. Please try again.', 'em-smtp-relay'),
                    esc_html__('Encryption Error', 'em-smtp-relay'),
                    ['response' => 500]
                );
            }
        }

        $currentData = $this->config->getGeneralSettings();
        return $currentData['em_smtp_password'] ?? '';
    }

    private function displayErrors(array $errors): void
    {
        echo '';
        foreach ($errors as $error) {
            echo '' . esc_html($error) . '';
        }
        echo '';
    }

    private function displaySuccess(string $message): void
    {
        echo ''
            . esc_html($message)
            . '';
    }
}