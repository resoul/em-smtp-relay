<?php

declare(strict_types=1);

namespace Emercury\Smtp\Contracts;

interface NonceManagerInterface
{
    public function verify(string $action, string $name = '_wpnonce'): bool;
    public function verifyWithCapability(
        string $action,
        string $capability = 'manage_options',
        string $name = '_wpnonce'
    ): bool;
    public function field(string $action, string $name = '_wpnonce', bool $referer = true, bool $echo = true): string;
}
