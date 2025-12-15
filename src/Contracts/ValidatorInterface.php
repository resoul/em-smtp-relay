<?php

declare(strict_types=1);

namespace Emercury\Smtp\Contracts;

use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;
use Emercury\Smtp\Config\DTO\AdvancedSettingsDTO;

interface ValidatorInterface
{
    public function validateEmail(string $email): bool;

    public function validateSmtpSettings(SmtpSettingsDTO $data): array;

    public function validateAdvancedSettings(AdvancedSettingsDTO $data): array;
}
