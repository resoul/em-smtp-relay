<?php

declare(strict_types=1);

namespace Emercury\Smtp\Contracts;

interface RateLimiterInterface
{
    /**
     * Check if action is allowed under rate limit
     *
     * @param string $identifier Unique identifier for the action
     * @return bool True if allowed, false if limit exceeded
     */
    public function checkLimit(string $identifier): bool;

    /**
     * Reset rate limit for identifier
     *
     * @param string $identifier Unique identifier
     * @return void
     */
    public function resetLimit(string $identifier): void;
}