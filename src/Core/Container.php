<?php

declare(strict_types=1);

namespace Emercury\Smtp\Core;

use Emercury\Smtp\Security\Encryption;
use Emercury\Smtp\Security\Validator;
use Emercury\Smtp\Security\NonceManager;
use Emercury\Smtp\Security\RateLimiter;
use Emercury\Smtp\Config\Config;
use Emercury\Smtp\Admin\SettingsPage;
use Emercury\Smtp\Admin\Tabs\GeneralTab;
use Emercury\Smtp\Admin\Tabs\AdvancedTab;
use Emercury\Smtp\Admin\Tabs\TestEmailTab;

class Container
{
    private array $services = [];
    private array $singletons = [];

    public function __construct()
    {
        $this->registerServices();
    }

    private function registerServices(): void
    {
        // Config
        $this->singleton(Config::class, fn() => new Config());

        // Security
        $this->singleton(Encryption::class, fn() => new Encryption());
        $this->singleton(Validator::class, fn() => new Validator());
        $this->singleton(NonceManager::class, fn() => new NonceManager());
        $this->singleton(RateLimiter::class, fn() => new RateLimiter());

        // Core
        $this->singleton(Mailer::class, fn() => new Mailer(
            $this->get(Encryption::class),
            $this->get(Validator::class),
            $this->get(Config::class)
        ));

        // Admin
        $this->singleton(GeneralTab::class, fn() => new GeneralTab(
            $this->get(Encryption::class),
            $this->get(Validator::class),
            $this->get(NonceManager::class),
            $this->get(Config::class),
            $this->get(RateLimiter::class)
        ));

        $this->singleton(AdvancedTab::class, fn() => new AdvancedTab(
            $this->get(Validator::class),
            $this->get(NonceManager::class),
            $this->get(Config::class)
        ));

        $this->singleton(TestEmailTab::class, fn() => new TestEmailTab(
            $this->get(NonceManager::class),
            $this->get(Config::class),
            $this->get(RateLimiter::class)
        ));

        $this->singleton(SettingsPage::class, fn() => new SettingsPage(
            $this->get(GeneralTab::class),
            $this->get(AdvancedTab::class),
            $this->get(TestEmailTab::class)
        ));
    }

    public function singleton(string $id, callable $factory): void
    {
        $this->services[$id] = $factory;
    }

    public function get(string $id): object
    {
        if (!isset($this->singletons[$id])) {
            if (!isset($this->services[$id])) {
                throw new \RuntimeException("Service {$id} not found");
            }
            $this->singletons[$id] = $this->services[$id]();
        }

        return $this->singletons[$id];
    }
}