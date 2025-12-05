<?php

declare(strict_types=1);

namespace Emercury\Smtp\Admin\Tabs;

use Emercury\Smtp\Config\Dto\SmtpSettingsDTO;
use Emercury\Smtp\Security\Encryption;
use Emercury\Smtp\Security\Validator;
use Emercury\Smtp\Security\NonceManager;
use Emercury\Smtp\Config\Config;
use Emercury\Smtp\Security\RateLimiter;
use Emercury\Smtp\Admin\AdminNotifier;

class GeneralTab
{
    private Encryption $encryption;
    private Validator $validator;
    private NonceManager $nonceManager;
    private Config $config;
    private RateLimiter $rateLimiter;
    private AdminNotifier $notifier;

    public function __construct(
        Encryption $encryption,
        Validator $validator,
        NonceManager $nonceManager,
        Config $config,
        RateLimiter $rateLimiter,
        AdminNotifier $notifier
    ) {
        $this->encryption = $encryption;
        $this->validator = $validator;
        $this->nonceManager = $nonceManager;
        $this->config = $config;
        $this->rateLimiter = $rateLimiter;
        $this->notifier = $notifier;
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
            $this->notifier->addError(
                __('Too many update attempts. Please wait before trying again.', 'em-smtp-relay')
            );
            return;
        }

        $dto = new SmtpSettingsDTO(
            $_POST['smtp_username'],
            $_POST['smtp_password'],
            $_POST['encryption'],
            $_POST['from_email'],
            $_POST['from_name'],
            $_POST['force_from_address'],
        );
        $this->validator->sanitizeSettings($dto);
        $errors = $this->validator->validateSmtpSettings($dto);

        if (!empty($errors)) {
            $this->notifier->addErrors($errors);
            return;
        }

        $this->saveSettings($dto);
    }

    private function saveSettings(SmtpSettingsDTO $dto): void
    {
        $dto->smtpPassword = $this->processPassword($dto->smtpPassword);
        $dto->smtpPort = $this->config->getSmtpPort($dto->smtpEncryption);
        $this->config->saveGeneralSettings($dto);

        $this->notifier->addSuccess(__('Settings Saved!', 'em-smtp-relay'));
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
        return $currentData->smtpPassword;
    }
}