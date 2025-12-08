<?php

declare(strict_types=1);

namespace Emercury\Smtp\Config\DTO;

use Emercury\Smtp\App\RequestHandler;
use Emercury\Smtp\Config\SettingKeys;

class AdvancedSettingsDTO
{
    public string $replyToEmail;
    public string $replyToName;
    public int $forceReplyTo;
    public string $ccEmail;
    public string $ccName;
    public int $forceCc;
    public string $bccEmail;
    public string $bccName;
    public int $forceBcc;

    public function __construct(
        string $replyToEmail = '',
        string $replyToName = '',
        int $forceReplyTo = 0,
        string $ccEmail = '',
        string $ccName = '',
        int $forceCc = 0,
        string $bccEmail = '',
        string $bccName = '',
        int $forceBcc = 0
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

    /**
     * @param $data array<mixed>
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data[SettingKeys::REPLY_TO_EMAIL] ?? '',
            $data[SettingKeys::REPLY_TO_NAME] ?? '',
            $data[SettingKeys::FORCE_REPLY_TO] ?? 0,
            $data[SettingKeys::CC_EMAIL] ?? '',
            $data[SettingKeys::CC_NAME] ?? '',
            $data[SettingKeys::FORCE_CC] ?? 0,
            $data[SettingKeys::BCC_EMAIL] ?? '',
            $data[SettingKeys::BCC_NAME] ?? '',
            $data[SettingKeys::FORCE_BCC] ?? 0
        );
    }

    public static function fromRequest(RequestHandler $request): self
    {
        return new self(
            $request->getString(SettingKeys::REPLY_TO_EMAIL),
            $request->getString(SettingKeys::REPLY_TO_NAME),
            $request->getInt(SettingKeys::FORCE_REPLY_TO),
            $request->getString(SettingKeys::CC_EMAIL),
            $request->getString(SettingKeys::CC_NAME),
            $request->getInt(SettingKeys::FORCE_CC),
            $request->getString(SettingKeys::BCC_EMAIL),
            $request->getString(SettingKeys::BCC_NAME),
            $request->getInt(SettingKeys::FORCE_BCC)
        );
    }

    public function toArray(): array
    {
        return [
            SettingKeys::REPLY_TO_EMAIL => $this->replyToEmail,
            SettingKeys::REPLY_TO_NAME => $this->replyToName,
            SettingKeys::FORCE_REPLY_TO => $this->forceReplyTo,
            SettingKeys::CC_EMAIL => $this->ccEmail,
            SettingKeys::CC_NAME => $this->ccName,
            SettingKeys::FORCE_CC => $this->forceCc,
            SettingKeys::BCC_EMAIL => $this->bccEmail,
            SettingKeys::BCC_NAME => $this->bccName,
            SettingKeys::FORCE_BCC => $this->forceBcc,
        ];
    }
}
