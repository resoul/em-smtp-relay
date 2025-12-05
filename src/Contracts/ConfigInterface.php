<?php

declare(strict_types=1);

namespace Emercury\Smtp\Contracts;

use Emercury\Smtp\Config\Dto\SmtpSettingsDTO;
use Emercury\Smtp\Config\Dto\AdvancedSettingsDTO;

interface ConfigInterface
{
    /**
     * Get SMTP port based on encryption type
     *
     * @param string $encryption Encryption type (tls/ssl)
     * @return int Port number
     */
    public function getSmtpPort(string $encryption): int;

    /**
     * Get general SMTP settings
     *
     * @return SmtpSettingsDTO
     */
    public function getGeneralSettings(): SmtpSettingsDTO;

    /**
     * Get advanced email settings
     *
     * @return AdvancedSettingsDTO
     */
    public function getAdvancedSettings(): AdvancedSettingsDTO;

    /**
     * Save general settings
     *
     * @param SmtpSettingsDTO $data Settings to save
     * @return bool Success status
     */
    public function saveGeneralSettings(SmtpSettingsDTO $data): bool;

    /**
     * Save advanced settings
     *
     * @param AdvancedSettingsDTO $data Settings to save
     * @return bool Success status
     */
    public function saveAdvancedSettings(AdvancedSettingsDTO $data): bool;
}