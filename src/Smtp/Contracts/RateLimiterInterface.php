<?php

declare(strict_types=1);

namespace Emercury\Smtp\Contracts;

interface RateLimiterInterface
{
    public function checkLimit(string $identifier): bool;
    public function resetLimit(string $identifier): void;
}
