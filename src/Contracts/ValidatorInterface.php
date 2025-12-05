<?php

declare(strict_types=1);

namespace Emercury\Smtp\Contracts;

use Emercury\Smtp\Config\Dto\SmtpSettingsDTO;
use Emercury\Smtp\Config\Dto\AdvancedSettingsDTO;

interface ValidatorInterface
{
    /**
     * Validate email address
     *
     * @param string $email Email to validate
     * @return bool
     */
    public function validateEmail(string $email): bool;

    /**
     * Validate SMTP settings
     *
     * @param SmtpSettingsDTO $data Settings to validate
     * @return array Array of error messages (empty if valid)
     */
    public function validateSmtpSettings(SmtpSettingsDTO $data): array;

    /**
     * Sanitize SMTP settings
     *
     * @param SmtpSettingsDTO $data Settings to sanitize
     * @return void
     */
    public function sanitizeSettings(SmtpSettingsDTO $data): void;

    /**
     * Validate advanced settings
     *
     * @param AdvancedSettingsDTO $data Settings to validate
     * @return array Array of error messages (empty if valid)
     */
    public function validateAdvancedSettings(AdvancedSettingsDTO $data): array;

    /**
     * Sanitize advanced settings
     *
     * @param AdvancedSettingsDTO $data Settings to sanitize
     * @return void
     */
    public function sanitizeAdvancedSettings(AdvancedSettingsDTO $data): void;
}
