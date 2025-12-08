<?php

declare(strict_types=1);

namespace Emercury\Smtp\App;

use Emercury\Smtp\Config\DTO\AdvancedSettingsDTO;
use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;
use Emercury\Smtp\Contracts\ValidatorInterface;

class Validator implements ValidatorInterface
{
    private $localization;

    public function __construct(Localization $localization)
    {
        $this->localization = $localization;
    }

    public function validateEmail(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public function validateSmtpSettings(SmtpSettingsDTO $data): array
    {
        $errors = [];

        if (empty($data->smtpUsername)) {
            $errors[] = $this->localization->t('SMTP Username is required');
        }

        if (!empty($data->fromEmail) && !$this->validateEmail($data->fromEmail)) {
            $errors[] = $this->localization->t('Invalid From Email Address');
        }

        if (!in_array($data->smtpEncryption ?? '', ['tls', 'ssl'], true)) {
            $errors[] = $this->localization->t('Invalid encryption type');
        }

        return $errors;
    }

    public function validateAdvancedSettings(AdvancedSettingsDTO $data): array
    {
        $errors = [];

        if (empty($data->replyToEmail) || !$this->validateEmail($data->replyToEmail)) {
            $errors[] = $this->localization->t('Invalid Reply-To Email Address');
        }

        if (empty($data->ccEmail) || !$this->validateEmail($data->ccEmail)) {
            $errors[] = $this->localization->t('Invalid CC Email Address');
        }

        if (empty($data->bccEmail) || !$this->validateEmail($data->bccEmail)) {
            $errors[] = $this->localization->t('Invalid BCC Email Address');
        }

        return $errors;
    }
}