<?php

declare(strict_types=1);

namespace Emercury\Smtp\Core;

use Emercury\Smtp\Contracts\NonceManagerInterface;

class NonceManager implements NonceManagerInterface
{
    public function verify(string $action, string $name = '_wpnonce'): bool
    {
        $nonce = $_REQUEST[$name] ?? '';

        if (!wp_verify_nonce($nonce, $action)) {
            return false;
        }

        return true;
    }

    public function verifyWithCapability(string $action, string $capability = 'manage_options', string $name = '_wpnonce'): bool
    {
        if (!current_user_can($capability)) {
            return false;
        }

        return $this->verify($action, $name);
    }

    public function field(string $action, string $name = '_wpnonce', bool $referer = true, bool $echo = true): string
    {
        return wp_nonce_field($action, $name, $referer, $echo);
    }
}