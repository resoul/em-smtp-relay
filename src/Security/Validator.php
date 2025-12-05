<?php

declare(strict_types=1);

namespace Emercury\Smtp\Security;

class Validator
{
    public function validateEmail(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public function validateSmtpSettings(array $data): array
    {
        $errors = [];

        if (empty($data['smtp_username'])) {
            $errors[] = __('SMTP Username is required', 'em-smtp-relay');
        }

        if (empty($data['smtp_password'])) {
            $errors[] = __('SMTP Password is required', 'em-smtp-relay');
        }

        if (!empty($data['from_email']) && !$this->validateEmail($data['from_email'])) {
            $errors[] = __('Invalid From Email Address', 'em-smtp-relay');
        }

        if (!in_array($data['smtp_encryption'] ?? '', ['tls', 'ssl'], true)) {
            $errors[] = __('Invalid encryption type', 'em-smtp-relay');
        }

        return $errors;
    }

    public function sanitizeSettings(array $data): array
    {
        return [
            'smtp_username' => sanitize_text_field($data['smtp_username'] ?? ''),
            'smtp_password' => $data['smtp_password'] ?? '',
            'smtp_encryption' => sanitize_text_field($data['smtp_encryption'] ?? 'tls'),
            'from_email' => sanitize_email($data['from_email'] ?? ''),
            'from_name' => sanitize_text_field($data['from_name'] ?? ''),
            'force_from_address' => !empty($data['force_from_address']) ? 1 : 0,
        ];
    }

    public function validateAdvancedSettings(array $data): array
    {
        $errors = [];

        if (!empty($data['reply_to_email']) && !$this->validateEmail($data['reply_to_email'])) {
            $errors[] = __('Invalid Reply-To Email Address', 'em-smtp-relay');
        }

        if (!empty($data['cc_email']) && !$this->validateEmail($data['cc_email'])) {
            $errors[] = __('Invalid CC Email Address', 'em-smtp-relay');
        }

        if (!empty($data['bcc_email']) && !$this->validateEmail($data['bcc_email'])) {
            $errors[] = __('Invalid BCC Email Address', 'em-smtp-relay');
        }

        return $errors;
    }

    public function sanitizeAdvancedSettings(array $data): array
    {
        return [
            'reply_to_email' => sanitize_email($data['reply_to_email'] ?? ''),
            'reply_to_name' => sanitize_text_field($data['reply_to_name'] ?? ''),
            'force_reply_to' => !empty($data['force_reply_to']) ? 1 : 0,
            'cc_email' => sanitize_email($data['cc_email'] ?? ''),
            'cc_name' => sanitize_text_field($data['cc_name'] ?? ''),
            'force_cc' => !empty($data['force_cc']) ? 1 : 0,
            'bcc_email' => sanitize_email($data['bcc_email'] ?? ''),
            'bcc_name' => sanitize_text_field($data['bcc_name'] ?? ''),
            'force_bcc' => !empty($data['force_bcc']) ? 1 : 0,
        ];
    }
}