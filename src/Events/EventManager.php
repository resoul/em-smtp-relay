<?php

declare(strict_types=1);

namespace Emercury\Smtp\Events;

class EventManager
{
    private static ?EventManager $instance = null;
    private array $listeners = [];

    public static function getInstance(): EventManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function listen(string $event, callable $callback, int $priority = 10): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        if (!isset($this->listeners[$event][$priority])) {
            $this->listeners[$event][$priority] = [];
        }

        $this->listeners[$event][$priority][] = $callback;
    }

    public function dispatch(string $event, ...$args): void
    {
        do_action("em_smtp_{$event}", ...$args);

        if (!isset($this->listeners[$event])) {
            return;
        }

        ksort($this->listeners[$event]);

        foreach ($this->listeners[$event] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                call_user_func_array($callback, $args);
            }
        }
    }

    public function remove(string $event, callable $callback): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $priority => $callbacks) {
            foreach ($callbacks as $key => $registeredCallback) {
                if ($registeredCallback === $callback) {
                    unset($this->listeners[$event][$priority][$key]);
                }
            }
        }
    }
}