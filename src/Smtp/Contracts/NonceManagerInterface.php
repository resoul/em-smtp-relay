<?php

declare(strict_types=1);

namespace Emercury\Smtp\Contracts;

interface NonceManagerInterface
{
    /**
     * Verify nonce
     *
     * @param string $action Nonce action
     * @param string $name Nonce field name
     * @return bool
     */
    public function verify(string $action, string $name = '_wpnonce'): bool;

    /**
     * Verify nonce with capability check
     *
     * @param string $action Nonce action
     * @param string $capability Required capability
     * @param string $name Nonce field name
     * @return bool
     */
    public function verifyWithCapability(
        string $action,
        string $capability = 'manage_options',
        string $name = '_wpnonce'
    ): bool;

    /**
     * Generate nonce field
     *
     * @param string $action Nonce action
     * @param string $name Nonce field name
     * @param bool $referer Include referer field
     * @param bool $echo Echo or return
     * @return string
     */
    public function field(
        string $action,
        string $name = '_wpnonce',
        bool $referer = true,
        bool $echo = true
    ): string;
}