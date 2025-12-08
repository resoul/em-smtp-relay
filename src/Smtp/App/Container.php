<?php

declare(strict_types=1);

namespace Emercury\Smtp\App;

use Emercury\Smtp\Admin\AdminNotifier;
use Emercury\Smtp\Admin\AdvancedStatistics;
use Emercury\Smtp\Admin\DashboardWidget;
use Emercury\Smtp\Admin\EmailStatistics;
use Emercury\Smtp\Admin\SettingsPage;
use Emercury\Smtp\Admin\StatisticsPage;
use Emercury\Smtp\Admin\Tabs\AdvancedTab;
use Emercury\Smtp\Admin\Tabs\ConfigManagerTab;
use Emercury\Smtp\Admin\Tabs\GeneralTab;
use Emercury\Smtp\Admin\Tabs\TestEmailTab;
use Emercury\Smtp\Config\Config;
use Emercury\Smtp\Contracts\ConfigInterface;
use Emercury\Smtp\Contracts\EmailLoggerInterface;
use Emercury\Smtp\Contracts\EmailStatisticsInterface;
use Emercury\Smtp\Contracts\EncryptionInterface;
use Emercury\Smtp\Contracts\NonceManagerInterface;
use Emercury\Smtp\Contracts\RateLimiterInterface;
use Emercury\Smtp\Contracts\ValidatorInterface;

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
        $this->singleton(Localization::class, fn() => new Localization(ConfigInterface::TEXT_DOMAIN));
        $this->singleton(DatabaseManager::class, fn() => new DatabaseManager());
        $this->singleton(EventManager::class, fn() => EventManager::getInstance());

        $this->singleton(ConfigInterface::class, fn() => new Config());
        $this->singleton(Config::class, fn() => $this->get(ConfigInterface::class));

        $this->singleton(EmailLoggerInterface::class, fn() => new EmailLogger(
            $this->get(DatabaseManager::class),
            $this->get(EventManager::class)
        ));
        $this->singleton(EmailLogger::class, fn() => $this->get(EmailLoggerInterface::class));

        $this->singleton(EmailStatisticsInterface::class, fn() => new EmailStatistics(
            $this->get(EmailLoggerInterface::class)
        ));
        $this->singleton(EmailStatistics::class, fn() => $this->get(EmailStatisticsInterface::class));

        $this->singleton(AdvancedStatistics::class, fn() => new AdvancedStatistics(
            $this->get(EmailLoggerInterface::class)
        ));

        $this->singleton(EncryptionInterface::class, fn() => new Encryption());
        $this->singleton(Encryption::class, fn() => $this->get(EncryptionInterface::class));

        $this->singleton(ValidatorInterface::class, fn() => new Validator(
            $this->get(Localization::class),
        ));
        $this->singleton(Validator::class, fn() => $this->get(ValidatorInterface::class));

        $this->singleton(NonceManagerInterface::class, fn() => new NonceManager());
        $this->singleton(NonceManager::class, fn() => $this->get(NonceManagerInterface::class));

        $this->singleton(RateLimiterInterface::class, fn() => new RateLimiter());
        $this->singleton(RateLimiter::class, fn() => $this->get(RateLimiterInterface::class));

        $this->singleton(AdminNotifier::class, fn() => new AdminNotifier());

        $this->singleton(RequestHandler::class, fn() => new RequestHandler());

        $this->singleton(StatisticsPage::class, fn() => new StatisticsPage(
            $this->get(AdvancedStatistics::class),
            $this->get(Localization::class),
        ));

        $this->singleton(DashboardWidget::class, fn() => new DashboardWidget(
            $this->get(EmailStatisticsInterface::class),
            $this->get(Localization::class),
            $this->get(ConfigInterface::class)
        ));

        $this->singleton(Mailer::class, fn() => new Mailer(
            $this->get(EncryptionInterface::class),
            $this->get(ConfigInterface::class),
            $this->get(EmailLoggerInterface::class),
            $this->get(EventManager::class)
        ));

        $this->singleton(GeneralTab::class, fn() => new GeneralTab(
            $this->get(EncryptionInterface::class),
            $this->get(ValidatorInterface::class),
            $this->get(NonceManagerInterface::class),
            $this->get(ConfigInterface::class),
            $this->get(RateLimiterInterface::class),
            $this->get(AdminNotifier::class),
            $this->get(Localization::class),
            $this->get(RequestHandler::class),
        ));

        $this->singleton(AdvancedTab::class, fn() => new AdvancedTab(
            $this->get(ValidatorInterface::class),
            $this->get(NonceManagerInterface::class),
            $this->get(ConfigInterface::class),
            $this->get(RequestHandler::class),
            $this->get(Localization::class),
            $this->get(AdminNotifier::class)
        ));

        $this->singleton(TestEmailTab::class, fn() => new TestEmailTab(
            $this->get(NonceManagerInterface::class),
            $this->get(ConfigInterface::class),
            $this->get(RateLimiterInterface::class),
            $this->get(Localization::class),
            $this->get(AdminNotifier::class)
        ));

        $this->singleton(ConfigManagerTab::class, fn() => new ConfigManagerTab(
            $this->get(ConfigInterface::class),
            $this->get(NonceManagerInterface::class),
            $this->get(Localization::class),
            $this->get(AdminNotifier::class)
        ));

        $this->singleton(SettingsPage::class, fn() => new SettingsPage(
            $this->get(GeneralTab::class),
            $this->get(AdvancedTab::class),
            $this->get(TestEmailTab::class),
            $this->get(Localization::class),
            $this->get(ConfigManagerTab::class)
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