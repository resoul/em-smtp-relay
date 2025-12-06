<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Security;

use Emercury\Smtp\Security\RateLimiter;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;

class RateLimiterTest extends TestCase
{
    private RateLimiter $rateLimiter;
    private array $transients = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->transients = [];

        Functions\when('get_transient')->alias(function($key) {
            return $this->transients[$key] ?? false;
        });

        Functions\when('set_transient')->alias(function($key, $value, $expiration) {
            $this->transients[$key] = $value;
            return true;
        });

        Functions\when('delete_transient')->alias(function($key) {
            unset($this->transients[$key]);
            return true;
        });

        $this->rateLimiter = new RateLimiter();
    }

    public function testCheckLimitAllowsFirstAttempt(): void
    {
        $result = $this->rateLimiter->checkLimit('test-action');
        $this->assertTrue($result);
    }

    public function testCheckLimitBlocksAfterMaxAttempts(): void
    {
        $identifier = 'test-action';

        for ($i = 0; $i < 20; $i++) {
            $this->rateLimiter->checkLimit($identifier);
        }

        $result = $this->rateLimiter->checkLimit($identifier);
        $this->assertFalse($result);
    }

    public function testResetLimitClearsAttempts(): void
    {
        $identifier = 'test-action';

        for ($i = 0; $i < 20; $i++) {
            $this->rateLimiter->checkLimit($identifier);
        }

        $this->rateLimiter->resetLimit($identifier);

        $result = $this->rateLimiter->checkLimit($identifier);
        $this->assertTrue($result);
    }
}