<?php

declare(strict_types=1);

namespace Emercury\Smtp\Admin\Tabs;

use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;
use Emercury\Smtp\Contracts\ConfigInterface;
use Emercury\Smtp\Contracts\EncryptionInterface;
use Emercury\Smtp\Contracts\LocalizationInterface;
use Emercury\Smtp\Contracts\NonceManagerInterface;
use Emercury\Smtp\Contracts\ValidatorInterface;
use Emercury\Smtp\Admin\AdminNotifier;
use Emercury\Smtp\Core\RequestHandler;

class GeneralTab
{
    private EncryptionInterface $encryption;
    private ValidatorInterface $validator;
    private NonceManagerInterface $nonceManager;
    private ConfigInterface $config;
    private AdminNotifier $notifier;
    private RequestHandler $request;
    private LocalizationInterface $localization;

    public function __construct(
        EncryptionInterface $encryption,
        ValidatorInterface $validator,
        NonceManagerInterface $nonceManager,
        ConfigInterface $config,
        AdminNotifier $notifier,
        LocalizationInterface $localization,
        RequestHandler $request
    ) {
        $this->encryption = $encryption;
        $this->validator = $validator;
        $this->nonceManager = $nonceManager;
        $this->config = $config;
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
                $this->localization->esc('Security check failed. Please try again.'),
                $this->localization->esc('Security Error'),
                ['response' => 403]
            );
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
                    $this->localization->esc('Failed to encrypt password. Please try again.'),
                    $this->localization->esc('Encryption Error'),
                    ['response' => 500]
                );
            }
        }

        $currentData = $this->config->getGeneralSettings();
        return $currentData->smtpPassword;
    }
}