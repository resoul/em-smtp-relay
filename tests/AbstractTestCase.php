<?php

declare(strict_types=1);

namespace Emercury\Tests;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

abstract class AbstractTestCase extends TestCase
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

    protected function mock(string $class): MockInterface
    {
        return Mockery::mock($class);
    }

    protected function partialMock(string $class): Mockery\LegacyMockInterface
    {
        return Mockery::mock($class)->makePartial();
    }

    protected function spy(string $class): MockInterface
    {
        return Mockery::spy($class);
    }
}
