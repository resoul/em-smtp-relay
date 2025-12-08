<?php

declare(strict_types=1);

namespace Emercury\Smtp\Contracts;

interface EncryptionInterface
{
    /**
     * Encrypt sensitive data
     *
     * @param string $data Data to encrypt
     * @return string Encrypted data
     * @throws \RuntimeException If encryption fails
     */
    public function encrypt(string $data): string;

    /**
     * Decrypt encrypted data
     *
     * @param string $data Encrypted data
     * @return string Decrypted data
     */
    public function decrypt(string $data): string;
}