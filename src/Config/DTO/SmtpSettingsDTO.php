<?php

declare(strict_types=1);

namespace Emercury\Smtp\Config\Dto;

use Emercury\Smtp\Config\Config;
use Emercury\Smtp\Config\SettingKeys;

class SmtpSettingsDTO
{
    public string $smtpUsername;
    public string $smtpPassword;
    public string $smtpEncryption;
    public string $fromEmail;
    public string $fromName;
    public bool $forceFromAddress;
    public int $smtpPort;
    private string $smtpHost = Config::SMTP_HOST;
    private string $smtpAuth = 'true';

    public function __construct(
        string $smtpUsername = '',
        string $smtpPassword = '',
        string $smtpEncryption = 'tls',
        string $fromEmail = '',
        string $fromName = '',
        bool $forceFromAddress = false
    ) {
        $this->smtpUsername     = $smtpUsername;
        $this->smtpPassword     = $smtpPassword;
        $this->smtpEncryption   = $smtpEncryption;
        $this->fromEmail        = $fromEmail;
        $this->fromName         = $fromName;
        $this->forceFromAddress = $forceFromAddress;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data[SettingKeys::USERNAME] ?? '',
            $data[SettingKeys::PASSWORD] ?? '',
            $data[SettingKeys::ENCRYPTION] ?? 'tls',
            $data[SettingKeys::FROM_EMAIL] ?? '',
            $data[SettingKeys::FROM_NAME] ?? '',
            (bool) ($data[SettingKeys::FORCE_FROM_ADDRESS] ?? false)
        );
    }

    public function toArray(): array
    {
        return [
            SettingKeys::USERNAME => $this->smtpUsername,
            SettingKeys::PASSWORD => $this->smtpPassword,
            SettingKeys::ENCRYPTION => $this->smtpEncryption,
            SettingKeys::FROM_EMAIL => $this->fromEmail,
            SettingKeys::FROM_NAME => $this->fromName,
            SettingKeys::FORCE_FROM_ADDRESS => (int) $this->forceFromAddress,
            SettingKeys::HOST => $this->smtpHost,
            SettingKeys::AUTH => $this->smtpAuth,
            SettingKeys::PORT => $this->smtpPort,
        ];
    }
}