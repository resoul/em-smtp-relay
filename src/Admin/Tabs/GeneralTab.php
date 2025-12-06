<?php

declare(strict_types=1);

namespace Emercury\Smtp\Admin\Tabs;

use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;
use Emercury\Smtp\Contracts\ConfigInterface;
use Emercury\Smtp\Contracts\EncryptionInterface;
use Emercury\Smtp\Contracts\NonceManagerInterface;
use Emercury\Smtp\Contracts\RateLimiterInterface;
use Emercury\Smtp\Contracts\ValidatorInterface;
use Emercury\Smtp\Admin\AdminNotifier;
use Emercury\Smtp\Core\RequestHandler;

class GeneralTab
{
    private EncryptionInterface $encryption;
    private ValidatorInterface $validator;
    private NonceManagerInterface $nonceManager;
    private ConfigInterface $config;
    private RateLimiterInterface $rateLimiter;
    private AdminNotifier $notifier;
    private RequestHandler $request;

    public function __construct(
        EncryptionInterface $encryption,
        ValidatorInterface $validator,
        NonceManagerInterface $nonceManager,
        ConfigInterface $config,
        RateLimiterInterface $rateLimiter,
        AdminNotifier $notifier,
        RequestHandler $request
    ) {
        $this->encryption = $encryption;
        $this->validator = $validator;
        $this->nonceManager = $nonceManager;
        $this->config = $config;
        $this->rateLimiter = $rateLimiter;
        $this->notifier = $notifier;
        $this->request = $request;
        $this->init();
    }

    protected function init(): void
    {
        add_action('admin_init', function () {
            if ($this->request->has('em_smtp_relay_update_settings')) {
                $this->handleSubmit();
            }
        });
    }

    public function render(): void
    {
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

        $dto = SmtpSettingsDTO::fromRequest($this->request);
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