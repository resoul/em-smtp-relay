<?php

declare(strict_types=1);

namespace Emercury\Smtp\Security;

use Emercury\Smtp\Config\DTO\AdvancedSettingsDTO;
use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;
use Emercury\Smtp\Contracts\ValidatorInterface;

class Validator implements ValidatorInterface
{
    public function validateEmail(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public function validateSmtpSettings(SmtpSettingsDTO $data): array
    {
        $errors = [];

        if (empty($data->smtpUsername)) {
            $errors[] = __('SMTP Username is required', 'em-smtp-relay');
        }

        if (empty($data->smtpPassword)) {
            $errors[] = __('SMTP Password is required', 'em-smtp-relay');
        }

        if (!empty($data->fromEmail) && !$this->validateEmail($data->fromEmail)) {
            $errors[] = __('Invalid From Email Address', 'em-smtp-relay');
        }

        if (!in_array($data->smtpEncryption ?? '', ['tls', 'ssl'], true)) {
            $errors[] = __('Invalid encryption type', 'em-smtp-relay');
        }

        return $errors;
    }

    public function sanitizeSettings(SmtpSettingsDTO $data): void
    {
        $data->smtpUsername = sanitize_text_field($data->smtpUsername);
        $data->smtpEncryption = sanitize_text_field($data->smtpEncryption);
        $data->forceFromAddress = !empty($data->forceFromAddress);
        $data->fromEmail = sanitize_email($data->fromEmail);
        $data->fromName = sanitize_text_field($data->fromName);
    }

    public function validateAdvancedSettings(AdvancedSettingsDTO $data): array
    {
        $errors = [];

        if (empty($data->replyToEmail) || !$this->validateEmail($data->replyToEmail)) {
            $errors[] = __('Invalid Reply-To Email Address', 'em-smtp-relay');
        }

        if (empty($data->ccEmail) || !$this->validateEmail($data->ccEmail)) {
            $errors[] = __('Invalid CC Email Address', 'em-smtp-relay');
        }

        if (empty($data->bccEmail) || !$this->validateEmail($data->bccEmail)) {
            $errors[] = __('Invalid BCC Email Address', 'em-smtp-relay');
        }

        return $errors;
    }

    public function sanitizeAdvancedSettings(AdvancedSettingsDTO $data): void
    {
        $data->forceReplyTo = !empty($data->forceReplyTo);
        $data->forceCc = !empty($data->forceCc);
        $data->forceBcc = !empty($data->forceBcc);
        $data->replyToEmail = sanitize_email($data->replyToEmail);
        $data->ccEmail = sanitize_email($data->ccEmail);
        $data->bccEmail = sanitize_email($data->bccEmail);
        $data->replyToName = sanitize_text_field($data->replyToName);
        $data->bccName = sanitize_text_field($data->bccName);
        $data->ccName = sanitize_text_field($data->ccName);
    }
}