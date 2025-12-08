<?php

declare(strict_types=1);

namespace Emercury\Smtp\Contracts;

use Emercury\Smtp\Config\DTO\AdvancedSettingsDTO;
use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;

interface ValidatorInterface
{
    public function validateEmail(string $email): bool;

    /**
     * @return array<mixed>
     */
    public function validateSmtpSettings(SmtpSettingsDTO $data): array;

    /**
     * @return array<mixed>
     */
    public function validateAdvancedSettings(AdvancedSettingsDTO $data): array;
}
