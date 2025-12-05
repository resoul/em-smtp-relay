<?php

declare(strict_types=1);

namespace Emercury\Smtp\Config;

class Config
{
    public const SMTP_HOST = 'smtp.emercury.net';
    public const OPTION_GENERAL = 'em_smtp_relay_data';
    public const OPTION_ADVANCED = 'em_smtp_relay_advanced_data';
    public const TEXT_DOMAIN = 'em-smtp-relay';

    public function getSmtpPort(string $encryption): int
    {
        return $encryption === 'ssl' ? 465 : 587;
    }

    public function getGeneralSettings(): array
    {
        $data = get_option(self::OPTION_GENERAL);

        if (!is_array($data)) {
            return $this->getDefaultGeneralSettings();
        }

        return $data;
    }

    public function getAdvancedSettings(): array
    {
        $data = get_option(self::OPTION_ADVANCED);

        if (!is_array($data)) {
            return $this->getDefaultAdvancedSettings();
        }

        return $data;
    }

    public function saveGeneralSettings(array $data): bool
    {
        return update_option(self::OPTION_GENERAL, $data);
    }

    public function saveAdvancedSettings(array $data): bool
    {
        return update_option(self::OPTION_ADVANCED, $data);
    }

    private function getDefaultGeneralSettings(): array
    {
        return [
            'em_smtp_username' => '',
            'em_smtp_password' => '',
            'em_smtp_encryption' => 'tls',
            'em_smtp_from_email' => '',
            'em_smtp_from_name' => '',
            'em_smtp_force_from_address' => 0,
        ];
    }

    private function getDefaultAdvancedSettings(): array
    {
        return [
            'em_smtp_reply_to_email' => '',
            'em_smtp_reply_to_name' => '',
            'em_smtp_force_reply_to' => 0,
            'em_smtp_cc_email' => '',
            'em_smtp_cc_name' => '',
            'em_smtp_force_cc' => 0,
            'em_smtp_bcc_email' => '',
            'em_smtp_bcc_name' => '',
            'em_smtp_force_bcc' => 0,
        ];
    }
}