<?php

declare(strict_types=1);

namespace Emercury\Smtp\Contracts;

use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;
use Emercury\Smtp\Config\DTO\AdvancedSettingsDTO;

interface ConfigInterface
{
    public const SMTP_HOST = 'smtp.emercury.net';
    public const OPTION_GENERAL = 'em_smtp_relay_data';
    public const OPTION_ADVANCED = 'em_smtp_relay_advanced_data';
    public const TEXT_DOMAIN = 'em-smtp-relay';

    public function getSmtpPort(string $encryption): int;
    public function getGeneralSettings(): SmtpSettingsDTO;
    public function getAdvancedSettings(): AdvancedSettingsDTO;
    public function saveGeneralSettings(SmtpSettingsDTO $data): bool;
    public function saveAdvancedSettings(AdvancedSettingsDTO $data): bool;
}