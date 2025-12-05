<?php

declare(strict_types=1);

namespace Emercury\Smtp\Config\Dto;

use Emercury\Smtp\Config\SettingKeys;

class AdvancedSettingsDTO
{
    public string $replyToEmail;
    public string $replyToName;
    public bool $forceReplyTo;
    public string $ccEmail;
    public string $ccName;
    public bool $forceCc;
    public string $bccEmail;
    public string $bccName;
    public bool $forceBcc;

    public function __construct(
        string $replyToEmail = '',
        string $replyToName = '',
        bool $forceReplyTo = false,
        string $ccEmail = '',
        string $ccName = '',
        bool $forceCc = false,
        string $bccEmail = '',
        string $bccName = '',
        bool $forceBcc = false
    ) {
        $this->replyToEmail = $replyToEmail;
        $this->replyToName = $replyToName;
        $this->forceReplyTo = $forceReplyTo;
        $this->ccEmail = $ccEmail;
        $this->ccName = $ccName;
        $this->forceCc = $forceCc;
        $this->bccEmail = $bccEmail;
        $this->bccName = $bccName;
        $this->forceBcc = $forceBcc;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data[SettingKeys::REPLY_TO_EMAIL] ?? '',
            $data[SettingKeys::REPLY_TO_NAME] ?? '',
            (bool) ($data[SettingKeys::FORCE_REPLY_TO] ?? false),
            $data[SettingKeys::CC_EMAIL] ?? '',
            $data[SettingKeys::CC_NAME] ?? '',
            (bool) ($data[SettingKeys::FORCE_CC] ?? false),
            $data[SettingKeys::BCC_EMAIL] ?? '',
            $data[SettingKeys::BCC_NAME] ?? '',
            (bool) ($data[SettingKeys::FORCE_BCC] ?? false),
        );
    }

    public function toArray(): array
    {
        return [
            SettingKeys::REPLY_TO_EMAIL => $this->replyToEmail,
            SettingKeys::REPLY_TO_NAME => $this->replyToName,
            SettingKeys::FORCE_REPLY_TO => (int) $this->forceReplyTo,
            SettingKeys::CC_EMAIL => $this->ccEmail,
            SettingKeys::CC_NAME => $this->ccName,
            SettingKeys::FORCE_CC => (int) $this->forceCc,
            SettingKeys::BCC_EMAIL => $this->bccEmail,
            SettingKeys::BCC_NAME => $this->bccName,
            SettingKeys::FORCE_BCC => (int) $this->forceBcc,
        ];
    }
}