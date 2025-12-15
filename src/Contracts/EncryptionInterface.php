<?php

declare(strict_types=1);

namespace Emercury\Smtp\Contracts;

interface EncryptionInterface
{
    public function encrypt(string $data): string;
    public function decrypt(string $data): string;
}