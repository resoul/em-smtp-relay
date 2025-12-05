<?php

declare(strict_types=1);

namespace Emercury\Smtp\Admin\Tabs;

use Emercury\Smtp\Config\Dto\AdvancedSettingsDTO;
use Emercury\Smtp\Security\Validator;
use Emercury\Smtp\Security\NonceManager;
use Emercury\Smtp\Config\Config;
use Emercury\Smtp\Admin\AdminNotifier;

class AdvancedTab
{
    private Validator $validator;
    private NonceManager $nonceManager;
    private Config $config;
    private AdminNotifier $notifier;

    public function __construct(
        Validator $validator,
        NonceManager $nonceManager,
        Config $config,
        AdminNotifier $notifier
    ) {
        $this->validator = $validator;
        $this->nonceManager = $nonceManager;
        $this->config = $config;
        $this->notifier = $notifier;
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

        $dto = new AdvancedSettingsDTO(
            $_POST['reply_to_email'],
            $_POST['reply_to_name'],
            $_POST['force_reply_to'],
            $_POST['cc_email'],
            $_POST['cc_name'],
            $_POST['force_cc'],
            $_POST['bcc_email'],
            $_POST['bcc_name'],
            $_POST['force_bcc']
        );

        $errors = $this->validator->validateAdvancedSettings($dto);

        if (!empty($errors)) {
            $this->notifier->addErrors($errors);
            return;
        }

        $this->validator->sanitizeAdvancedSettings($dto);
        $this->config->saveAdvancedSettings($dto);

        $this->notifier->addSuccess(__('Settings Saved!', 'em-smtp-relay'));
    }
}