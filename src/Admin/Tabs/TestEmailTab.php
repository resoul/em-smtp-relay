<?php

declare(strict_types=1);

namespace Emercury\Smtp\Admin\Tabs;

use Emercury\Smtp\Security\NonceManager;
use Emercury\Smtp\Config\Config;
use Emercury\Smtp\Security\RateLimiter;
use Emercury\Smtp\Admin\AdminNotifier;

class TestEmailTab
{
    private NonceManager $nonceManager;
    private Config $config;
    private RateLimiter $rateLimiter;
    private AdminNotifier $notifier;

    public function __construct(
        NonceManager $nonceManager,
        Config $config,
        RateLimiter $rateLimiter,
        AdminNotifier $notifier
    ) {
        $this->nonceManager = $nonceManager;
        $this->config = $config;
        $this->rateLimiter = $rateLimiter;
        $this->notifier = $notifier;
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
            $this->notifier->addError(
                __('Too many test emails sent. Please wait before trying again.', 'em-smtp-relay')
            );
            return;
        }

        $to = sanitize_email($_POST['em_smtp_relay_to_email'] ?? '');
        $subject = sanitize_text_field($_POST['em_smtp_relay_email_subject'] ?? 'Emercury SMTP Test Email');
        $message = wp_kses_post($_POST['em_smtp_relay_email_body'] ?? 'If you receive this email, Emercury SMTP is working correctly.');

        if (!$this->validateRecipient($to)) {
            $this->notifier->addError(
                __('Please enter a valid email address.', 'em-smtp-relay')
            );
            return;
        }

        if (!$this->validateSmtpConfiguration()) {
            $this->notifier->addError(
                __('Please setup SMTP settings first.', 'em-smtp-relay')
            );
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

        return !empty($data->smtpUsername)
            && !empty($data->smtpPassword)
            && !empty($data->fromEmail);
    }

    private function sendTestEmail(string $to, string $subject, string $message): void
    {
        $headers = [];

        if (!empty($_POST['debug'])) {
            $headers[] = 'EM-SMTP-Debug: True';
        }

        if (wp_mail($to, $subject, $message, $headers)) {
            $this->notifier->addSuccess(__('Test email has been sent successfully!', 'em-smtp-relay'));
        } else {
            $this->notifier->addError(__('Failed to send test email. Please check your settings.', 'em-smtp-relay'));
        }
    }
}