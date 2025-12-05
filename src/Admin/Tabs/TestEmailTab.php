<?php

declare(strict_types=1);

namespace Emercury\Smtp\Admin\Tabs;

use Emercury\Smtp\Security\NonceManager;
use Emercury\Smtp\Config\Config;
use Emercury\Smtp\Security\RateLimiter;

class TestEmailTab
{
    private NonceManager $nonceManager;
    private Config $config;
    private RateLimiter $rateLimiter;

    public function __construct(
        NonceManager $nonceManager,
        Config $config,
        RateLimiter $rateLimiter
    ) {
        $this->nonceManager = $nonceManager;
        $this->config = $config;
        $this->rateLimiter = $rateLimiter;
    }

    public function render(): void
    {
        if (isset($_POST['em_smtp_relay_send_test_email'])) {
            $this->handleTestEmail();
        }

        include EM_SMTP_PATH . 'templates/test-email-tab.php';
    }

    private function handleTestEmail(): void
    {
        if (!$this->nonceManager->verifyWithCapability('em_smtp_relay_test_email', 'manage_options')) {
            wp_die(
                esc_html__('Security check failed. Please try again.', 'em-smtp-relay'),
                esc_html__('Security Error', 'em-smtp-relay'),
                ['response' => 403]
            );
        }

        $userId = get_current_user_id();
        if (!$this->rateLimiter->checkLimit('test_email_' . $userId)) {
            $this->displayError(
                __('Too many test emails sent. Please wait before trying again.', 'em-smtp-relay')
            );
            return;
        }

        $to = sanitize_email($_POST['em_smtp_relay_to_email'] ?? '');
        $subject = sanitize_text_field($_POST['em_smtp_relay_email_subject'] ?? '');
        $message = sanitize_textarea_field($_POST['em_smtp_relay_email_body'] ?? '');

        if (!$this->validateRecipient($to)) {
            $this->displayError(__('Please enter a valid email address.', 'em-smtp-relay'));
            return;
        }

        if (!$this->validateSmtpConfiguration()) {
            $this->displayError(__('Please setup SMTP settings first.', 'em-smtp-relay'));
            return;
        }

        $this->sendTestEmail($to, $subject, $message);
    }

    private function validateRecipient(string $to): bool
    {
        return !empty($to) && is_email($to);
    }

    private function validateSmtpConfiguration(): bool
    {
        $data = $this->config->getGeneralSettings();

        return !empty($data['em_smtp_username'])
            && !empty($data['em_smtp_password'])
            && !empty($data['em_smtp_from_email']);
    }

    private function sendTestEmail(string $to, string $subject, string $message): void
    {
        $headers = [];

        if (!empty($_POST['debug'])) {
            $headers[] = 'EM-SMTP-Debug: True';
        }

        if (wp_mail($to, $subject, $message, $headers)) {
            $this->displaySuccess(__('Test email has been sent successfully!', 'em-smtp-relay'));
        } else {
            $this->displayError(__('Failed to send test email. Please check your settings.', 'em-smtp-relay'));
        }
    }

    private function displayError(string $message): void
    {
        echo ''
            . esc_html($message)
            . '';
    }

    private function displaySuccess(string $message): void
    {
        echo ''
            . esc_html($message)
            . '';
    }
}