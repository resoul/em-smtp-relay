<?php

declare(strict_types=1);

namespace Emercury\Smtp\Admin\Tabs;

use Emercury\Smtp\Admin\AdminNotifier;
use Emercury\Smtp\App\Localization;
use Emercury\Smtp\App\RequestHandler;
use Emercury\Smtp\Config\DTO\AdvancedSettingsDTO;
use Emercury\Smtp\Contracts\ConfigInterface;
use Emercury\Smtp\Contracts\NonceManagerInterface;
use Emercury\Smtp\Contracts\ValidatorInterface;

class AdvancedTab
{
    private ValidatorInterface $validator;
    private NonceManagerInterface $nonceManager;
    private ConfigInterface $config;
    private AdminNotifier $notifier;
    private RequestHandler $request;
    private Localization $localization;

    public function __construct(
        ValidatorInterface $validator,
        NonceManagerInterface $nonceManager,
        ConfigInterface $config,
        RequestHandler $request,
        Localization $localization,
        AdminNotifier $notifier,
    ) {
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
            if ($this->request->has('em_smtp_relay_update_advanced_settings')) {
                $this->handleFormSubmission();
            }
        });
    }

    public function render(): void
    {
        $data = $this->config->getAdvancedSettings();

        include EM_SMTP_PATH . 'templates/admin/advanced-tab.php';
    }

    private function handleFormSubmission(): void
    {
        if (!$this->nonceManager->verifyWithCapability('em_smtp_relay_advanced_settings')) {
            wp_die(
                $this->localization->escHtml('Security check failed. Please try again.'),
                $this->localization->escHtml('Security Error'),
                ['response' => 403]
            );
        }

        $dto = AdvancedSettingsDTO::fromRequest($this->request);
        $errors = $this->validator->validateAdvancedSettings($dto);

        if (!empty($errors)) {
            $this->notifier->addErrors($errors);
            return;
        }

        $this->config->saveAdvancedSettings($dto);

        $this->notifier->addSuccess($this->localization->t('Settings Saved!'));
    }
}
