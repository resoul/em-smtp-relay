<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Brain\Monkey;

/**
 * Base test case for unit tests
 */
abstract class TestCase extends BaseTestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    protected function mock(string $class): \Mockery\MockInterface
    {
        return \Mockery::mock($class);
    }

    protected function partialMock(string $class): \Mockery\MockInterface
    {
        return \Mockery::mock($class)->makePartial();
    }

    protected function spy(string $class): \Mockery\MockInterface
    {
        return \Mockery::spy($class);
    }
}