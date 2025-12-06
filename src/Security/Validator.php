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
}