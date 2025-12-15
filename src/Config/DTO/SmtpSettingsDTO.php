<?php

declare(strict_types=1);

namespace Emercury\Smtp\Config\DTO;

use Emercury\Smtp\Config\Config;
use Emercury\Smtp\Config\SettingKeys;
use Emercury\Smtp\Core\RequestHandler;

class SmtpSettingsDTO
{
    public string $smtpUsername;
    public string $smtpPassword;
    public string $smtpEncryption;
    public string $fromEmail;
    public string $fromName;
    public int $forceFromAddress;
    public int $smtpPort;
    public string $smtpHost;
    private string $smtpAuth = 'true';

    public function __construct(
        string $smtpUsername = '',
        string $smtpPassword = '',
        string $smtpEncryption = 'tls',
        string $fromEmail = '',
        string $fromName = '',
        string $host = '',
        int $forceFromAddress = 0,
        int $smtpPort = 0
    ) {
        $this->smtpUsername     = $smtpUsername;
        $this->smtpPassword     = $smtpPassword;
        $this->smtpEncryption   = $smtpEncryption;
        $this->fromEmail        = $fromEmail;
        $this->fromName         = $fromName;
        $this->smtpHost         = $host;
        $this->forceFromAddress = $forceFromAddress;
        $this->smtpPort         = $smtpPort;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data[SettingKeys::USERNAME] ?? '',
            $data[SettingKeys::PASSWORD] ?? '',
            $data[SettingKeys::ENCRYPTION] ?? 'tls',
            $data[SettingKeys::FROM_EMAIL] ?? '',
            $data[SettingKeys::FROM_NAME] ?? '',
           $data[SettingKeys::HOST] ?? '',
           $data[SettingKeys::FORCE_FROM_ADDRESS] ?? 0,
            (int) ($data[SettingKeys::PORT] ?? 0)
        );
    }

    public static function fromRequest(RequestHandler $request): self
    {
        return new self(
            $request->getEmail(SettingKeys::USERNAME),
            $request->getString(SettingKeys::PASSWORD),
            $request->getString(SettingKeys::ENCRYPTION, 'tls'),
            $request->getEmail(SettingKeys::FROM_EMAIL),
            $request->getString(SettingKeys::FROM_NAME),
            $request->getString(SettingKeys::HOST),
            $request->getInt(SettingKeys::FORCE_FROM_ADDRESS),
            $request->getInt(SettingKeys::PORT),
//            $request->getInt(SettingKeys::PORT, $smtpEncryption === 'ssl' ? 465 : 587),
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
            SettingKeys::FORCE_FROM_ADDRESS => $this->forceFromAddress,
            SettingKeys::HOST => $this->smtpHost,
            SettingKeys::AUTH => $this->smtpAuth,
            SettingKeys::PORT => $this->smtpPort,
        ];
    }
}