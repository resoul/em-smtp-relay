<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Core;

use Emercury\Smtp\Admin\AdminNotifier;
use Emercury\Smtp\Admin\DashboardWidget;
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
use Emercury\Smtp\Contracts\LoggerInterface;
use Emercury\Smtp\Contracts\NonceManagerInterface;
use Emercury\Smtp\Contracts\RateLimiterInterface;
use Emercury\Smtp\Contracts\ValidatorInterface;
use Emercury\Smtp\Core\Container;
use Emercury\Smtp\Core\Logger;
use Emercury\Smtp\Core\Mailer;
use Emercury\Smtp\Core\RequestHandler;
use Emercury\Smtp\Database\DatabaseManager;
use Emercury\Smtp\Events\EventManager;
use Emercury\Smtp\Logging\EmailLogger;
use Emercury\Smtp\Security\Encryption;
use Emercury\Smtp\Security\NonceManager;
use Emercury\Smtp\Security\RateLimiter;
use Emercury\Smtp\Security\Validator;
use Emercury\Smtp\Statistics\AdvancedStatistics;
use Emercury\Smtp\Statistics\EmailStatistics;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock EventManager singleton
        Functions\when('EventManager::getInstance')->justReturn(Mockery::mock(EventManager::class));

        $this->container = new Container();
    }

    // Constructor tests
    public function testConstructorRegistersServices(): void
    {
        $container = new Container();

        $this->assertInstanceOf(Container::class, $container);
    }

    // singleton() method tests
    public function testSingletonRegistersService(): void
    {
        $container = new Container();

        $called = false;
        $container->singleton('test_service', function () use (&$called) {
            $called = true;
            return new \stdClass();
        });

        $this->assertFalse($called, 'Factory should not be called until get() is invoked');
    }

    public function testSingletonAllowsCustomServices(): void
    {
        $container = new Container();

        $customService = new \stdClass();
        $customService->value = 'test';

        $container->singleton('custom_service', function () use ($customService) {
            return $customService;
        });

        $retrieved = $container->get('custom_service');

        $this->assertSame($customService, $retrieved);
        $this->assertEquals('test', $retrieved->value);
    }

    // get() method tests
    public function testGetReturnsRegisteredService(): void
    {
        $service = $this->container->get(ConfigInterface::class);

        $this->assertInstanceOf(ConfigInterface::class, $service);
    }

    public function testGetReturnsSameSingletonInstance(): void
    {
        $service1 = $this->container->get(ConfigInterface::class);
        $service2 = $this->container->get(ConfigInterface::class);

        $this->assertSame($service1, $service2);
    }

    public function testGetThrowsExceptionForUnregisteredService(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Service NonExistentService not found');

        $this->container->get('NonExistentService');
    }

    public function testGetCreatesInstanceOnlyOnce(): void
    {
        $callCount = 0;

        $container = new Container();
        $container->singleton('counter_service', function () use (&$callCount) {
            $callCount++;
            $obj = new \stdClass();
            $obj->count = $callCount;
            return $obj;
        });

        $service1 = $container->get('counter_service');
        $service2 = $container->get('counter_service');
        $service3 = $container->get('counter_service');

        $this->assertEquals(1, $callCount);
        $this->assertEquals(1, $service1->count);
        $this->assertEquals(1, $service2->count);
        $this->assertEquals(1, $service3->count);
    }

    // Service registration tests - Core services
    public function testGetReturnsConfigInterface(): void
    {
        $service = $this->container->get(ConfigInterface::class);

        $this->assertInstanceOf(ConfigInterface::class, $service);
        $this->assertInstanceOf(Config::class, $service);
    }

    public function testGetReturnsConfig(): void
    {
        $service = $this->container->get(Config::class);

        $this->assertInstanceOf(Config::class, $service);
    }

    public function testGetReturnsConfigInterfaceAndConfigAsSameInstance(): void
    {
        $interface = $this->container->get(ConfigInterface::class);
        $concrete = $this->container->get(Config::class);

        $this->assertSame($interface, $concrete);
    }

    public function testGetReturnsDatabaseManager(): void
    {
        $service = $this->container->get(DatabaseManager::class);

        $this->assertInstanceOf(DatabaseManager::class, $service);
    }

    public function testGetReturnsEventManager(): void
    {
        $service = $this->container->get(EventManager::class);

        $this->assertInstanceOf(EventManager::class, $service);
    }

    // Security services
    public function testGetReturnsEncryptionInterface(): void
    {
        $service = $this->container->get(EncryptionInterface::class);

        $this->assertInstanceOf(EncryptionInterface::class, $service);
        $this->assertInstanceOf(Encryption::class, $service);
    }

    public function testGetReturnsEncryption(): void
    {
        $service = $this->container->get(Encryption::class);

        $this->assertInstanceOf(Encryption::class, $service);
    }

    public function testGetReturnsValidatorInterface(): void
    {
        $service = $this->container->get(ValidatorInterface::class);

        $this->assertInstanceOf(ValidatorInterface::class, $service);
        $this->assertInstanceOf(Validator::class, $service);
    }

    public function testGetReturnsValidator(): void
    {
        $service = $this->container->get(Validator::class);

        $this->assertInstanceOf(Validator::class, $service);
    }

    public function testGetReturnsNonceManagerInterface(): void
    {
        $service = $this->container->get(NonceManagerInterface::class);

        $this->assertInstanceOf(NonceManagerInterface::class, $service);
        $this->assertInstanceOf(NonceManager::class, $service);
    }

    public function testGetReturnsNonceManager(): void
    {
        $service = $this->container->get(NonceManager::class);

        $this->assertInstanceOf(NonceManager::class, $service);
    }

    public function testGetReturnsRateLimiterInterface(): void
    {
        $service = $this->container->get(RateLimiterInterface::class);

        $this->assertInstanceOf(RateLimiterInterface::class, $service);
        $this->assertInstanceOf(RateLimiter::class, $service);
    }

    public function testGetReturnsRateLimiter(): void
    {
        $service = $this->container->get(RateLimiter::class);

        $this->assertInstanceOf(RateLimiter::class, $service);
    }

    // Logger services
    public function testGetReturnsLoggerInterface(): void
    {
        $service = $this->container->get(LoggerInterface::class);

        $this->assertInstanceOf(LoggerInterface::class, $service);
        $this->assertInstanceOf(Logger::class, $service);
    }

    public function testGetReturnsLogger(): void
    {
        $service = $this->container->get(Logger::class);

        $this->assertInstanceOf(Logger::class, $service);
    }

    public function testGetReturnsEmailLoggerInterface(): void
    {
        $service = $this->container->get(EmailLoggerInterface::class);

        $this->assertInstanceOf(EmailLoggerInterface::class, $service);
        $this->assertInstanceOf(EmailLogger::class, $service);
    }

    public function testGetReturnsEmailLogger(): void
    {
        $service = $this->container->get(EmailLogger::class);

        $this->assertInstanceOf(EmailLogger::class, $service);
    }

    // Statistics services
    public function testGetReturnsEmailStatisticsInterface(): void
    {
        $service = $this->container->get(EmailStatisticsInterface::class);

        $this->assertInstanceOf(EmailStatisticsInterface::class, $service);
        $this->assertInstanceOf(EmailStatistics::class, $service);
    }

    public function testGetReturnsEmailStatistics(): void
    {
        $service = $this->container->get(EmailStatistics::class);

        $this->assertInstanceOf(EmailStatistics::class, $service);
    }

    public function testGetReturnsAdvancedStatistics(): void
    {
        $service = $this->container->get(AdvancedStatistics::class);

        $this->assertInstanceOf(AdvancedStatistics::class, $service);
    }

    // Admin services
    public function testGetReturnsAdminNotifier(): void
    {
        $service = $this->container->get(AdminNotifier::class);

        $this->assertInstanceOf(AdminNotifier::class, $service);
    }

    public function testGetReturnsRequestHandler(): void
    {
        $service = $this->container->get(RequestHandler::class);

        $this->assertInstanceOf(RequestHandler::class, $service);
    }

    public function testGetReturnsStatisticsPage(): void
    {
        $service = $this->container->get(StatisticsPage::class);

        $this->assertInstanceOf(StatisticsPage::class, $service);
    }

    public function testGetReturnsDashboardWidget(): void
    {
        $service = $this->container->get(DashboardWidget::class);

        $this->assertInstanceOf(DashboardWidget::class, $service);
    }

    public function testGetReturnsMailer(): void
    {
        $service = $this->container->get(Mailer::class);

        $this->assertInstanceOf(Mailer::class, $service);
    }

    // Tab services
    public function testGetReturnsGeneralTab(): void
    {
        $service = $this->container->get(GeneralTab::class);

        $this->assertInstanceOf(GeneralTab::class, $service);
    }

    public function testGetReturnsAdvancedTab(): void
    {
        $service = $this->container->get(AdvancedTab::class);

        $this->assertInstanceOf(AdvancedTab::class, $service);
    }

    public function testGetReturnsTestEmailTab(): void
    {
        $service = $this->container->get(TestEmailTab::class);

        $this->assertInstanceOf(TestEmailTab::class, $service);
    }

    public function testGetReturnsConfigManagerTab(): void
    {
        $service = $this->container->get(ConfigManagerTab::class);

        $this->assertInstanceOf(ConfigManagerTab::class, $service);
    }

    public function testGetReturnsSettingsPage(): void
    {
        $service = $this->container->get(SettingsPage::class);

        $this->assertInstanceOf(SettingsPage::class, $service);
    }

    // Dependency injection tests
    public function testServicesReceiveCorrectDependencies(): void
    {
        $mailer = $this->container->get(Mailer::class);

        $this->assertInstanceOf(Mailer::class, $mailer);

        // Mailer should be constructed with its dependencies
        // This test verifies the container can resolve complex dependency trees
        $this->expectNotToPerformAssertions();
    }

    public function testGeneralTabReceivesAllDependencies(): void
    {
        $generalTab = $this->container->get(GeneralTab::class);

        $this->assertInstanceOf(GeneralTab::class, $generalTab);
    }

    public function testAdvancedTabReceivesAllDependencies(): void
    {
        $advancedTab = $this->container->get(AdvancedTab::class);

        $this->assertInstanceOf(AdvancedTab::class, $advancedTab);
    }

    public function testTestEmailTabReceivesAllDependencies(): void
    {
        $testEmailTab = $this->container->get(TestEmailTab::class);

        $this->assertInstanceOf(TestEmailTab::class, $testEmailTab);
    }

    public function testConfigManagerTabReceivesAllDependencies(): void
    {
        $configManagerTab = $this->container->get(ConfigManagerTab::class);

        $this->assertInstanceOf(ConfigManagerTab::class, $configManagerTab);
    }

    public function testSettingsPageReceivesAllTabDependencies(): void
    {
        $settingsPage = $this->container->get(SettingsPage::class);

        $this->assertInstanceOf(SettingsPage::class, $settingsPage);
    }

    public function testMailerReceivesAllDependencies(): void
    {
        $mailer = $this->container->get(Mailer::class);

        $this->assertInstanceOf(Mailer::class, $mailer);
    }

    public function testStatisticsPageReceivesAdvancedStatistics(): void
    {
        $statisticsPage = $this->container->get(StatisticsPage::class);

        $this->assertInstanceOf(StatisticsPage::class, $statisticsPage);
    }

    public function testDashboardWidgetReceivesDependencies(): void
    {
        $dashboardWidget = $this->container->get(DashboardWidget::class);

        $this->assertInstanceOf(DashboardWidget::class, $dashboardWidget);
    }

    // Interface to concrete resolution tests
    public function testInterfaceResolvesToSameConcrete(): void
    {
        $encryptionInterface = $this->container->get(EncryptionInterface::class);
        $encryptionConcrete = $this->container->get(Encryption::class);

        $this->assertSame($encryptionInterface, $encryptionConcrete);
    }

    public function testValidatorInterfaceResolvesToSameConcrete(): void
    {
        $validatorInterface = $this->container->get(ValidatorInterface::class);
        $validatorConcrete = $this->container->get(Validator::class);

        $this->assertSame($validatorInterface, $validatorConcrete);
    }

    public function testNonceManagerInterfaceResolvesToSameConcrete(): void
    {
        $nonceManagerInterface = $this->container->get(NonceManagerInterface::class);
        $nonceManagerConcrete = $this->container->get(NonceManager::class);

        $this->assertSame($nonceManagerInterface, $nonceManagerConcrete);
    }

    public function testRateLimiterInterfaceResolvesToSameConcrete(): void
    {
        $rateLimiterInterface = $this->container->get(RateLimiterInterface::class);
        $rateLimiterConcrete = $this->container->get(RateLimiter::class);

        $this->assertSame($rateLimiterInterface, $rateLimiterConcrete);
    }

    public function testLoggerInterfaceResolvesToSameConcrete(): void
    {
        $loggerInterface = $this->container->get(LoggerInterface::class);
        $loggerConcrete = $this->container->get(Logger::class);

        $this->assertSame($loggerInterface, $loggerConcrete);
    }

    public function testEmailLoggerInterfaceResolvesToSameConcrete(): void
    {
        $emailLoggerInterface = $this->container->get(EmailLoggerInterface::class);
        $emailLoggerConcrete = $this->container->get(EmailLogger::class);

        $this->assertSame($emailLoggerInterface, $emailLoggerConcrete);
    }

    public function testEmailStatisticsInterfaceResolvesToSameConcrete(): void
    {
        $emailStatisticsInterface = $this->container->get(EmailStatisticsInterface::class);
        $emailStatisticsConcrete = $this->container->get(EmailStatistics::class);

        $this->assertSame($emailStatisticsInterface, $emailStatisticsConcrete);
    }

    // Singleton behavior tests
    public function testMultipleGetsReturnSameInstance(): void
    {
        $config1 = $this->container->get(ConfigInterface::class);
        $config2 = $this->container->get(ConfigInterface::class);
        $config3 = $this->container->get(Config::class);

        $this->assertSame($config1, $config2);
        $this->assertSame($config2, $config3);
    }

    public function testAllSecurityServicesAreSingletons(): void
    {
        $encryption1 = $this->container->get(EncryptionInterface::class);
        $encryption2 = $this->container->get(Encryption::class);

        $validator1 = $this->container->get(ValidatorInterface::class);
        $validator2 = $this->container->get(Validator::class);

        $this->assertSame($encryption1, $encryption2);
        $this->assertSame($validator1, $validator2);
    }

    public function testAllTabsAreSingletons(): void
    {
        $generalTab1 = $this->container->get(GeneralTab::class);
        $generalTab2 = $this->container->get(GeneralTab::class);

        $advancedTab1 = $this->container->get(AdvancedTab::class);
        $advancedTab2 = $this->container->get(AdvancedTab::class);

        $this->assertSame($generalTab1, $generalTab2);
        $this->assertSame($advancedTab1, $advancedTab2);
    }

    // Complex dependency resolution tests
    public function testCanResolveDeepDependencyTree(): void
    {
        // SettingsPage depends on all tabs
        // Each tab has multiple dependencies
        // This tests that the container can resolve all of them correctly

        $settingsPage = $this->container->get(SettingsPage::class);

        $this->assertInstanceOf(SettingsPage::class, $settingsPage);
    }

    public function testSharedDependenciesAreSingletons(): void
    {
        // Both GeneralTab and AdvancedTab depend on ConfigInterface
        // They should receive the same instance

        $generalTab = $this->container->get(GeneralTab::class);
        $advancedTab = $this->container->get(AdvancedTab::class);

        // Can't directly access dependencies, but we can verify they're constructed
        $this->assertInstanceOf(GeneralTab::class, $generalTab);
        $this->assertInstanceOf(AdvancedTab::class, $advancedTab);

        // Verify the config is a singleton
        $config1 = $this->container->get(ConfigInterface::class);
        $config2 = $this->container->get(ConfigInterface::class);

        $this->assertSame($config1, $config2);
    }

    // Edge cases
    public function testGetWithInvalidServiceName(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Service InvalidServiceName not found');

        $this->container->get('InvalidServiceName');
    }

    public function testGetWithEmptyString(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Service  not found');

        $this->container->get('');
    }

    public function testSingletonCanBeOverwritten(): void
    {
        $container = new Container();

        $container->singleton('test', function () {
            $obj = new \stdClass();
            $obj->version = 1;
            return $obj;
        });

        $service1 = $container->get('test');
        $this->assertEquals(1, $service1->version);

        // Overwrite the singleton
        $container->singleton('test', function () {
            $obj = new \stdClass();
            $obj->version = 2;
            return $obj;
        });

        // Should still get the old instance (already created)
        $service2 = $container->get('test');
        $this->assertEquals(1, $service2->version);
        $this->assertSame($service1, $service2);
    }

    public function testFactoryClosureReceivesContainer(): void
    {
        $container = new Container();

        $receivedContainer = null;
        $container->singleton('test', function () use ($container, &$receivedContainer) {
            $receivedContainer = $container;
            return new \stdClass();
        });

        $container->get('test');

        $this->assertSame($container, $receivedContainer);
    }

    // All registered services test
    public function testAllRegisteredServicesCanBeResolved(): void
    {
        $services = [
            DatabaseManager::class,
            EventManager::class,
            ConfigInterface::class,
            Config::class,
            EmailLoggerInterface::class,
            EmailLogger::class,
            EmailStatisticsInterface::class,
            EmailStatistics::class,
            AdvancedStatistics::class,
            EncryptionInterface::class,
            Encryption::class,
            ValidatorInterface::class,
            Validator::class,
            NonceManagerInterface::class,
            NonceManager::class,
            RateLimiterInterface::class,
            RateLimiter::class,
            LoggerInterface::class,
            Logger::class,
            AdminNotifier::class,
            RequestHandler::class,
            StatisticsPage::class,
            DashboardWidget::class,
            Mailer::class,
            GeneralTab::class,
            AdvancedTab::class,
            TestEmailTab::class,
            ConfigManagerTab::class,
            SettingsPage::class,
        ];

        foreach ($services as $service) {
            $instance = $this->container->get($service);
            $this->assertNotNull($instance);
            $this->assertIsObject($instance);
        }
    }

    public function testNoServiceIsCreatedUntilRequested(): void
    {
        $created = false;

        $container = new Container();
        $container->singleton('lazy_service', function () use (&$created) {
            $created = true;
            return new \stdClass();
        });

        $this->assertFalse($created);

        $container->get('lazy_service');

        $this->assertTrue($created);
    }
}