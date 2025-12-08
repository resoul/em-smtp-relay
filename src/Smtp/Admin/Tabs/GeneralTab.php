<?php

declare(strict_types=1);

namespace Emercury\Smtp\Admin\Tabs;

use Emercury\Smtp\App\Localization;
use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;
use Emercury\Smtp\Contracts\ConfigInterface;
use Emercury\Smtp\Contracts\EncryptionInterface;
use Emercury\Smtp\Contracts\NonceManagerInterface;
use Emercury\Smtp\Contracts\RateLimiterInterface;
use Emercury\Smtp\Contracts\ValidatorInterface;
use Emercury\Smtp\Admin\AdminNotifier;
use Emercury\Smtp\App\RequestHandler;

class GeneralTab
{
    private EncryptionInterface $encryption;
    private ValidatorInterface $validator;
    private NonceManagerInterface $nonceManager;
    private ConfigInterface $config;
    private RateLimiterInterface $rateLimiter;
    private AdminNotifier $notifier;
    private RequestHandler $request;
    private Localization $localization;

    public function __construct(
        EncryptionInterface $encryption,
        ValidatorInterface $validator,
        NonceManagerInterface $nonceManager,
        ConfigInterface $config,
        RateLimiterInterface $rateLimiter,
        AdminNotifier $notifier,
        Localization $localization,
        RequestHandler $request
    ) {
        $this->encryption = $encryption;
        $this->validator = $validator;
        $this->nonceManager = $nonceManager;
        $this->config = $config;
        $this->rateLimiter = $rateLimiter;
        $this->notifier = $notifier;
        $this->request = $request;
        $this->localization = $localization;
        $this->init();
    }

    public function init(): void
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
                $this->localization->escHtml('Security check failed. Please try again.'),
                $this->localization->escHtml('Security Error'),
                ['response' => 403]
            );
        }

        $userId = get_current_user_id();

        if (!$this->rateLimiter->checkLimit('settings_update_' . $userId)) {
            $this->notifier->addError(
                $this->localization->t('Too many update attempts. Please wait before trying again.')
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

        $this->notifier->addSuccess($this->localization->t('Settings Saved!'));
    }

    private function processPassword(string $password): string
    {
        if (!empty($password)) {
            try {
                return $this->encryption->encrypt($password);
            } catch (\Exception $e) {
                wp_die(
                    $this->localization->escHtml('Failed to encrypt password. Please try again.'),
                    $this->localization->escHtml('Encryption Error'),
                    ['response' => 500]
                );
            }
        }

        $currentData = $this->config->getGeneralSettings();
        return $currentData->smtpPassword;
    }
}