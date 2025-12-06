<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests;

use WP_UnitTestCase;

abstract class IntegrationTestCase extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetGlobalState();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    protected function resetGlobalState(): void
    {
        wp_cache_flush();
        wp_cache_delete('alloptions', 'options');
    }

    protected function cleanupTestData(): void
    {
        // Override in child classes
    }

    protected function createTestUser(string $role = 'administrator'): int
    {
        return $this->factory()->user->create(['role' => $role]);
    }

    protected function actingAs(int $userId): void
    {
        wp_set_current_user($userId);
    }
}