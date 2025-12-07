<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Events;

use Emercury\Smtp\Events\EventManager;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;

class EventManagerTest extends TestCase
{
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset singleton between tests
        $reflection = new \ReflectionClass(EventManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $this->eventManager = EventManager::getInstance();
    }

    public function testGetInstanceReturnsSameInstance(): void
    {
        $instance1 = EventManager::getInstance();
        $instance2 = EventManager::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function testGetInstanceReturnsSingleton(): void
    {
        $instance = EventManager::getInstance();

        $this->assertInstanceOf(EventManager::class, $instance);
    }

    public function testListenRegistersCallback(): void
    {
        $called = false;
        $callback = function() use (&$called) {
            $called = true;
        };

        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_test_event');

        $this->eventManager->listen('test_event', $callback);
        $this->eventManager->dispatch('test_event');

        $this->assertTrue($called);
    }

    public function testListenWithMultipleCallbacks(): void
    {
        $calls = [];

        $callback1 = function() use (&$calls) {
            $calls[] = 'callback1';
        };

        $callback2 = function() use (&$calls) {
            $calls[] = 'callback2';
        };

        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_test_event');

        $this->eventManager->listen('test_event', $callback1);
        $this->eventManager->listen('test_event', $callback2);
        $this->eventManager->dispatch('test_event');

        $this->assertEquals(['callback1', 'callback2'], $calls);
    }

    public function testListenWithDifferentPriorities(): void
    {
        $calls = [];

        $callback1 = function() use (&$calls) {
            $calls[] = 'priority_10';
        };

        $callback2 = function() use (&$calls) {
            $calls[] = 'priority_5';
        };

        $callback3 = function() use (&$calls) {
            $calls[] = 'priority_20';
        };

        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_test_event');

        $this->eventManager->listen('test_event', $callback1, 10);
        $this->eventManager->listen('test_event', $callback2, 5);
        $this->eventManager->listen('test_event', $callback3, 20);
        $this->eventManager->dispatch('test_event');

        $this->assertEquals(['priority_5', 'priority_10', 'priority_20'], $calls);
    }

    public function testListenWithSamePriority(): void
    {
        $calls = [];

        $callback1 = function() use (&$calls) {
            $calls[] = 'first';
        };

        $callback2 = function() use (&$calls) {
            $calls[] = 'second';
        };

        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_test_event');

        $this->eventManager->listen('test_event', $callback1, 10);
        $this->eventManager->listen('test_event', $callback2, 10);
        $this->eventManager->dispatch('test_event');

        $this->assertEquals(['first', 'second'], $calls);
    }

    public function testDispatchCallsWordPressHook(): void
    {
        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_test_event', 'arg1', 'arg2');

        $this->eventManager->dispatch('test_event', 'arg1', 'arg2');
    }

    public function testDispatchPassesArgumentsToCallbacks(): void
    {
        $receivedArgs = [];

        $callback = function(...$args) use (&$receivedArgs) {
            $receivedArgs = $args;
        };

        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_test_event', 'arg1', 'arg2', 'arg3');

        $this->eventManager->listen('test_event', $callback);
        $this->eventManager->dispatch('test_event', 'arg1', 'arg2', 'arg3');

        $this->assertEquals(['arg1', 'arg2', 'arg3'], $receivedArgs);
    }

    public function testDispatchWithNoListeners(): void
    {
        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_no_listeners_event');

        $this->eventManager->dispatch('no_listeners_event');

        $this->assertTrue(true);
    }

    public function testDispatchWithComplexArguments(): void
    {
        $receivedData = null;

        $callback = function($data) use (&$receivedData) {
            $receivedData = $data;
        };

        $complexData = [
            'user' => ['id' => 1, 'name' => 'John'],
            'meta' => ['count' => 5]
        ];

        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_test_event', $complexData);

        $this->eventManager->listen('test_event', $callback);
        $this->eventManager->dispatch('test_event', $complexData);

        $this->assertEquals($complexData, $receivedData);
    }

    public function testRemoveCallbackFromEvent(): void
    {
        $called = false;
        $callback = function() use (&$called) {
            $called = true;
        };

        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_test_event');

        $this->eventManager->listen('test_event', $callback);
        $this->eventManager->remove('test_event', $callback);
        $this->eventManager->dispatch('test_event');

        $this->assertFalse($called);
    }

    public function testRemoveSpecificCallbackKeepsOthers(): void
    {
        $calls = [];

        $callback1 = function() use (&$calls) {
            $calls[] = 'callback1';
        };

        $callback2 = function() use (&$calls) {
            $calls[] = 'callback2';
        };

        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_test_event');

        $this->eventManager->listen('test_event', $callback1);
        $this->eventManager->listen('test_event', $callback2);
        $this->eventManager->remove('test_event', $callback1);
        $this->eventManager->dispatch('test_event');

        $this->assertEquals(['callback2'], $calls);
    }

    public function testRemoveNonExistentCallback(): void
    {
        $called = false;
        $callback = function() use (&$called) {
            $called = true;
        };

        $nonExistentCallback = function() {};

        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_test_event');

        $this->eventManager->listen('test_event', $callback);
        $this->eventManager->remove('test_event', $nonExistentCallback);
        $this->eventManager->dispatch('test_event');

        $this->assertTrue($called);
    }

    public function testRemoveFromNonExistentEvent(): void
    {
        $callback = function() {};

        $this->eventManager->remove('non_existent_event', $callback);

        $this->assertTrue(true);
    }

    public function testMultipleEventsAreIndependent(): void
    {
        $event1Called = false;
        $event2Called = false;

        $callback1 = function() use (&$event1Called) {
            $event1Called = true;
        };

        $callback2 = function() use (&$event2Called) {
            $event2Called = true;
        };

        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_event1');

        $this->eventManager->listen('event1', $callback1);
        $this->eventManager->listen('event2', $callback2);
        $this->eventManager->dispatch('event1');

        $this->assertTrue($event1Called);
        $this->assertFalse($event2Called);
    }

    public function testDispatchWithZeroArguments(): void
    {
        $called = false;
        $callback = function() use (&$called) {
            $called = true;
        };

        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_test_event');

        $this->eventManager->listen('test_event', $callback);
        $this->eventManager->dispatch('test_event');

        $this->assertTrue($called);
    }

    public function testDispatchWithManyArguments(): void
    {
        $receivedArgs = [];

        $callback = function(...$args) use (&$receivedArgs) {
            $receivedArgs = $args;
        };

        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_test_event', 1, 2, 3, 4, 5, 6, 7, 8, 9, 10);

        $this->eventManager->listen('test_event', $callback);
        $this->eventManager->dispatch('test_event', 1, 2, 3, 4, 5, 6, 7, 8, 9, 10);

        $this->assertCount(10, $receivedArgs);
        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $receivedArgs);
    }

    public function testCallbackCanModifySharedState(): void
    {
        $counter = 0;

        $callback1 = function() use (&$counter) {
            $counter++;
        };

        $callback2 = function() use (&$counter) {
            $counter += 10;
        };

        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_test_event');

        $this->eventManager->listen('test_event', $callback1);
        $this->eventManager->listen('test_event', $callback2);
        $this->eventManager->dispatch('test_event');

        $this->assertEquals(11, $counter);
    }

    public function testListenWithClassMethod(): void
    {
        $testObject = new class {
            public $called = false;

            public function handleEvent() {
                $this->called = true;
            }
        };

        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_test_event');

        $this->eventManager->listen('test_event', [$testObject, 'handleEvent']);
        $this->eventManager->dispatch('test_event');

        $this->assertTrue($testObject->called);
    }

    public function testPriorityOrderingWithManyListeners(): void
    {
        $calls = [];

        for ($i = 10; $i >= 1; $i--) {
            $priority = $i * 10;
            $callback = function() use (&$calls, $priority) {
                $calls[] = $priority;
            };
            $this->eventManager->listen('test_event', $callback, $priority);
        }

        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_test_event');

        $this->eventManager->dispatch('test_event');

        $expected = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
        $this->assertEquals($expected, $calls);
    }

    public function testEventNamePrefixing(): void
    {
        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_custom_event', 'test_arg')
            ->andReturnNull();

        $this->eventManager->dispatch('custom_event', 'test_arg');

        $this->assertTrue(true);
    }

    public function testRemoveCallbackFromSpecificPriority(): void
    {
        $calls = [];

        $callback1 = function() use (&$calls) {
            $calls[] = 'priority_10';
        };

        $callback2 = function() use (&$calls) {
            $calls[] = 'priority_20';
        };

        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_test_event');

        $this->eventManager->listen('test_event', $callback1, 10);
        $this->eventManager->listen('test_event', $callback2, 20);
        $this->eventManager->remove('test_event', $callback1);
        $this->eventManager->dispatch('test_event');

        $this->assertEquals(['priority_20'], $calls);
    }

    public function testCallbackReceivesCorrectArgumentTypes(): void
    {
        $receivedString = null;
        $receivedInt = null;
        $receivedArray = null;
        $receivedBool = null;

        $callback = function($str, $int, $arr, $bool) use (&$receivedString, &$receivedInt, &$receivedArray, &$receivedBool) {
            $receivedString = $str;
            $receivedInt = $int;
            $receivedArray = $arr;
            $receivedBool = $bool;
        };

        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_test_event', 'test', 123, ['key' => 'value'], true);

        $this->eventManager->listen('test_event', $callback);
        $this->eventManager->dispatch('test_event', 'test', 123, ['key' => 'value'], true);

        $this->assertEquals('test', $receivedString);
        $this->assertEquals(123, $receivedInt);
        $this->assertEquals(['key' => 'value'], $receivedArray);
        $this->assertTrue($receivedBool);
    }

    public function testMultipleDispatchesCallCallbacksMultipleTimes(): void
    {
        $callCount = 0;

        $callback = function() use (&$callCount) {
            $callCount++;
        };

        Functions\expect('do_action')
            ->times(3);

        $this->eventManager->listen('test_event', $callback);
        $this->eventManager->dispatch('test_event');
        $this->eventManager->dispatch('test_event');
        $this->eventManager->dispatch('test_event');

        $this->assertEquals(3, $callCount);
    }

    public function testSingletonPersistsListenersAcrossGetInstanceCalls(): void
    {
        $called = false;
        $callback = function() use (&$called) {
            $called = true;
        };

        $instance1 = EventManager::getInstance();
        $instance1->listen('test_event', $callback);

        Functions\expect('do_action')
            ->once()
            ->with('em_smtp_test_event');

        $instance2 = EventManager::getInstance();
        $instance2->dispatch('test_event');

        $this->assertTrue($called);
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(EventManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        parent::tearDown();
    }
}