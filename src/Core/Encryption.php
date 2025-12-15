<?php

declare(strict_types=1);

namespace Emercury\Smtp\Core;

use Emercury\Smtp\Contracts\EncryptionInterface;

class Encryption implements EncryptionInterface
{
    private const CIPHER_METHOD = 'AES-256-CBC';

    public function encrypt(string $data): string
    {
        if (empty($data)) {
            return '';
        }

        $key = $this->getEncryptionKey();
        $iv = substr(wp_salt('secure_auth'), 0, 16);

        $encrypted = openssl_encrypt(
            $data,
            self::CIPHER_METHOD,
            $key,
            0,
            $iv
        );

        return base64_encode($encrypted);
    }

    public function decrypt(string $data): string
    {
        if (empty($data)) {
            return '';
        }

        $key = $this->getEncryptionKey();
        $iv = substr(wp_salt('secure_auth'), 0, 16);

        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return '';
        }

        $decrypted = openssl_decrypt(
            $decoded,
            self::CIPHER_METHOD,
            $key,
            0,
            $iv
        );

        return $decrypted !== false ? $decrypted : '';
    }

    private function getEncryptionKey(): string
    {
        return hash('sha256', Encryption . phpwp_salt('auth') . wp_salt('secure_auth'));
    }
}