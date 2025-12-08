<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Admin\Tabs;

use Emercury\Smtp\Admin\AdminNotifier;
use Emercury\Smtp\Admin\Tabs\AdvancedTab;
use Emercury\Smtp\Config\DTO\AdvancedSettingsDTO;
use Emercury\Smtp\Contracts\ConfigInterface;
use Emercury\Smtp\Contracts\NonceManagerInterface;
use Emercury\Smtp\Contracts\ValidatorInterface;
use Emercury\Smtp\Core\RequestHandler;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class AdvancedTabTest extends TestCase
{
    private ValidatorInterface $validator;
    private NonceManagerInterface $nonceManager;
    private ConfigInterface $config;
    private AdminNotifier $notifier;
    private RequestHandler $request;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Мокаем все зависимости
        $this->validator = Mockery::mock(ValidatorInterface::class);
        $this->nonceManager = Mockery::mock(NonceManagerInterface::class);
        $this->config = Mockery::mock(ConfigInterface::class);
        $this->notifier = Mockery::mock(AdminNotifier::class);
        $this->request = Mockery::mock(RequestHandler::class);

        // 2. Имитируем функции WordPress
        Functions\when('add_action')->justReturn(true);
        Functions\when('__')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('wp_die')->alias(function($message) {
            throw new \Exception("WP Die: {$message}");
        });
    }

    protected function createAdvancedTab(): AdvancedTab
    {
        return new AdvancedTab(
            $this->validator,
            $this->nonceManager,
            $this->config,
            $this->request,
            $this->notifier
        );
    }

    // --- Тесты конструктора и инициализации ---

    public function testInitAddsAdminInitAction(): void
    {
        Functions\expect('add_action')
            ->once()
            ->with('admin_init', Mockery::type('\Closure'));

        $this->createAdvancedTab();
    }

    // --- Тесты рендеринга ---

    public function testRenderGetsSettingsAndIncludesTemplate(): void
    {
        // Устанавливаем фиктивную константу пути для require
        if (!defined('EM_SMTP_PATH')) {
            define('EM_SMTP_PATH', '/tmp/');
        }

        $mockSettings = new AdvancedSettingsDTO();

        $this->config
            ->shouldReceive('getAdvancedSettings')
            ->once()
            ->andReturn($mockSettings);

        // Мокаем require_once/include для шаблона
        $templatePath = EM_SMTP_PATH . 'templates/admin/advanced-tab.php';
        Functions\when('include')->alias(function($path) use ($templatePath) {
            $this->assertSame($templatePath, $path);
            return true;
        });

        $tab = $this->createAdvancedTab();
        $tab->render();
    }


    // --- Тесты обработки отправки формы (handleFormSubmission) ---

    public function testHandleFormSubmissionSkipsWhenNoSubmissionFlag(): void
    {
        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->once()
            ->andReturn(false);

        // Убеждаемся, что handleFormSubmission не вызывается
        $this->nonceManager->shouldNotReceive('verifyWithCapability');

        $this->createAdvancedTab();
    }

    // --- Тест: Неудача проверки Nonce ---

    public function testHandleFormSubmissionDiesOnFailedNonceVerification(): void
    {
        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->once()
            ->andReturn(true);

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->with('em_smtp_relay_advanced_settings')
            ->once()
            ->andReturn(false);

        // Ожидаем, что будет вызван wp_die()
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('WP Die: Security check failed. Please try again.');

        $tab = $this->createAdvancedTab();

        // Мы должны имитировать вызов action 'admin_init', который вызывает handleFormSubmission
        Functions\expect('add_action')
            ->once()
            ->with('admin_init', Mockery::on(function ($callback) {
                // Вызываем анонимную функцию-обработчик
                $callback();
                return true;
            }));

        // Создание объекта, которое инициирует хук
        $tab->init();
    }

    // --- Тест: Неудача валидации ---

    public function testHandleFormSubmissionHandlesValidationErrors(): void
    {
        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->once()
            ->andReturn(true);

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->with('em_smtp_relay_advanced_settings')
            ->once()
            ->andReturn(true);

        // 1. Мокаем DTO, которое будет создано
        $dto = Mockery::mock(AdvancedSettingsDTO::class);
        Functions\when(AdvancedSettingsDTO::class . '::fromRequest')
            ->with($this->request)
            ->once()
            ->andReturn($dto);

        $errors = ['log_age' => 'Must be a positive number'];

        // 2. Мокаем валидатор для возврата ошибок
        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->with($dto)
            ->once()
            ->andReturn($errors);

        // 3. Ожидаем, что ошибки будут добавлены, но настройки не будут сохранены
        $this->notifier
            ->shouldReceive('addErrors')
            ->with($errors)
            ->once();

        $this->config->shouldNotReceive('saveAdvancedSettings');

        $tab = $this->createAdvancedTab();

        Functions\expect('add_action')
            ->once()
            ->with('admin_init', Mockery::on(function ($callback) {
                $callback();
                return true;
            }));

        $tab->init();
    }

    // --- Тест: Успешная отправка и сохранение ---

    public function testHandleFormSubmissionSavesSettingsOnSuccess(): void
    {
        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->once()
            ->andReturn(true);

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->with('em_smtp_relay_advanced_settings')
            ->once()
            ->andReturn(true);

        // 1. Мокаем DTO
        $dto = Mockery::mock(AdvancedSettingsDTO::class);
        Functions\when(AdvancedSettingsDTO::class . '::fromRequest')
            ->with($this->request)
            ->once()
            ->andReturn($dto);

        // 2. Мокаем валидатор для возврата отсутствия ошибок
        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->with($dto)
            ->once()
            ->andReturn([]); // Пустой массив = успех

        // 3. Ожидаем сохранение настроек
        $this->config
            ->shouldReceive('saveAdvancedSettings')
            ->with($dto)
            ->once();

        // 4. Ожидаем добавление сообщения об успехе
        $this->notifier
            ->shouldReceive('addSuccess')
            ->with('Settings Saved!')
            ->once();

        $tab = $this->createAdvancedTab();

        Functions\expect('add_action')
            ->once()
            ->with('admin_init', Mockery::on(function ($callback) {
                $callback();
                return true;
            }));

        $tab->init();
    }
}