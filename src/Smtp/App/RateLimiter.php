<?php

declare(strict_types=1);

namespace Emercury\Smtp\App;

use Emercury\Smtp\Contracts\RateLimiterInterface;

class RateLimiter implements RateLimiterInterface
{
    private const OPTION_PREFIX = 'em_smtp_rate_limit_';
    private const MAX_ATTEMPTS = 20;
    private const TIME_WINDOW = 3600;

    public function checkLimit(string $identifier): bool
    {
        $key = self::OPTION_PREFIX . md5($identifier);
        $data = get_transient($key);

        if ($data === false) {
            $data = ['count' => 0, 'first_attempt' => time()];
        }

        $data['count']++;

        if ($data['count'] > self::MAX_ATTEMPTS) {
            return false;
        }

        set_transient($key, $data, self::TIME_WINDOW);
        return true;
    }

    public function resetLimit(string $identifier): void
    {
        $key = self::OPTION_PREFIX . md5($identifier);
        delete_transient($key);
    }
}
