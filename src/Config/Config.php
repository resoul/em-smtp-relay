<?php

declare(strict_types=1);

namespace Emercury\Smtp\Config;

use Emercury\Smtp\Config\DTO\AdvancedSettingsDTO;
use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;
use Emercury\Smtp\Contracts\ConfigInterface;

class Config implements ConfigInterface
{
    public const SMTP_HOST = 'smtp.emercury.net';
    public const OPTION_GENERAL = 'em_smtp_relay_data';
    public const OPTION_ADVANCED = 'em_smtp_relay_advanced_data';
    public const TEXT_DOMAIN = 'em-smtp-relay';

    public function getSmtpPort(string $encryption): int
    {
        return $encryption === 'ssl' ? 465 : 587;
    }

    public function getGeneralSettings(): SmtpSettingsDTO
    {
        $data = get_option(self::OPTION_GENERAL);

        if (!is_array($data)) {
            return $this->getDefaultGeneralSettings();
        }

        return SmtpSettingsDTO::fromArray($data);
    }

    public function getAdvancedSettings(): AdvancedSettingsDTO
    {
        $data = get_option(self::OPTION_ADVANCED);

        if (!is_array($data)) {
            return $this->getDefaultAdvancedSettings();
        }

        return AdvancedSettingsDTO::fromArray($data);
    }

    public function saveGeneralSettings(SmtpSettingsDTO $data): bool
    {
        if (empty($data->smtpPassword)) {
            $settings = $this->getGeneralSettings();
            $data->smtpPassword = $settings->smtpPassword;
        }

        return update_option(self::OPTION_GENERAL, $data->toArray());
    }

    public function saveAdvancedSettings(AdvancedSettingsDTO $data): bool
    {
        return update_option(self::OPTION_ADVANCED, $data->toArray());
    }

    private function getDefaultGeneralSettings(): SmtpSettingsDTO
    {
        return new SmtpSettingsDTO();
    }

    private function getDefaultAdvancedSettings(): AdvancedSettingsDTO
    {
        return new AdvancedSettingsDTO();
    }
}