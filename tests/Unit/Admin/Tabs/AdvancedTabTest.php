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
use Brain\Monkey\Actions;
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

        $this->validator = Mockery::mock(ValidatorInterface::class);
        $this->nonceManager = Mockery::mock(NonceManagerInterface::class);
        $this->config = Mockery::mock(ConfigInterface::class);
        $this->notifier = Mockery::mock(AdminNotifier::class);
        $this->request = Mockery::mock(RequestHandler::class);

        Functions\when('add_action')->justReturn(true);
        Functions\when('__')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('wp_die')->alias(function($message) {
            throw new \Exception("WP Die: {$message}");
        });

        if (!defined('EM_SMTP_PATH')) {
            define('EM_SMTP_PATH', __DIR__ . '/');
        }
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

    public function testInitAddsAdminInitAction(): void
    {
        Actions\expectAdded('admin_init')->once();

        $this->createAdvancedTab();
    }

    public function testRenderGetsSettingsAndIncludesTemplate(): void
    {
        $mockSettings = new AdvancedSettingsDTO();

        $this->config
            ->shouldReceive('getAdvancedSettings')
            ->once()
            ->andReturn($mockSettings);

        $templatePath = EM_SMTP_PATH . 'templates/admin/advanced-tab.php';
        if (!is_dir(dirname($templatePath))) {
            @mkdir(dirname($templatePath), 0777, true);
        }
        @file_put_contents($templatePath, '<?php // Mock template ?>');

        $tab = $this->createAdvancedTab();

        ob_start();
        $tab->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('Mock template', $output);

        @unlink($templatePath);
    }

    public function testHandleFormSubmissionSkipsWhenNoSubmissionFlag(): void
    {
        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->once()
            ->andReturn(false);

        $this->nonceManager->shouldNotReceive('verifyWithCapability');

        $tab = $this->createAdvancedTab();

        $reflection = new \ReflectionClass($tab);
        $method = $reflection->getMethod('init');
        $method->setAccessible(true);

        $this->expectNotToPerformAssertions();
    }

    public function testHandleFormSubmissionDiesOnFailedNonceVerification(): void
    {
        $requestData = ['em_smtp_relay_update_advanced_settings' => '1'];
        $this->request = new RequestHandler($requestData);

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->with('em_smtp_relay_advanced_settings')
            ->once()
            ->andReturn(false);

        Functions\expect('esc_html__')
            ->times(2)
            ->andReturnArg();

        $tab = new AdvancedTab(
            $this->validator,
            $this->nonceManager,
            $this->config,
            $this->request,
            $this->notifier
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Security check failed. Please try again.');

        $reflection = new \ReflectionClass($tab);
        $method = $reflection->getMethod('handleFormSubmission');
        $method->setAccessible(true);
        $method->invoke($tab);
    }

    public function testHandleFormSubmissionHandlesValidationErrors(): void
    {
        $requestData = [
            'em_smtp_relay_update_advanced_settings' => '1',
            'em_smtp_relay_reply_to_email' => 'reply@example.com',
            'em_smtp_relay_reply_to_name' => 'Reply Name',
        ];

        $this->request = new RequestHandler($requestData);

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->with('em_smtp_relay_advanced_settings')
            ->once()
            ->andReturn(true);

        $errors = ['reply_to_email' => 'Invalid email'];

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->once()
            ->with(Mockery::type(AdvancedSettingsDTO::class))
            ->andReturn($errors);

        $this->notifier
            ->shouldReceive('addErrors')
            ->with($errors)
            ->once();

        $this->config->shouldNotReceive('saveAdvancedSettings');

        $tab = new AdvancedTab(
            $this->validator,
            $this->nonceManager,
            $this->config,
            $this->request,
            $this->notifier
        );

        $reflection = new \ReflectionClass($tab);
        $method = $reflection->getMethod('handleFormSubmission');
        $method->setAccessible(true);
        $method->invoke($tab);
    }

    public function testHandleFormSubmissionSavesSettingsOnSuccess(): void
    {
        $requestData = [
            'em_smtp_relay_update_advanced_settings' => '1',
            'em_smtp_relay_reply_to_email' => 'reply@example.com',
            'em_smtp_relay_reply_to_name' => 'Reply Name',
            'em_smtp_relay_force_reply_to' => '1',
            'em_smtp_relay_cc_email' => 'cc@example.com',
            'em_smtp_relay_cc_name' => 'CC Name',
            'em_smtp_relay_force_cc' => '0',
            'em_smtp_relay_bcc_email' => 'bcc@example.com',
            'em_smtp_relay_bcc_name' => 'BCC Name',
            'em_smtp_relay_force_bcc' => '0',
        ];

        $this->request = new RequestHandler($requestData);

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->with('em_smtp_relay_advanced_settings')
            ->once()
            ->andReturn(true);

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->once()
            ->with(Mockery::type(AdvancedSettingsDTO::class))
            ->andReturn([]);

        $this->config
            ->shouldReceive('saveAdvancedSettings')
            ->once()
            ->with(Mockery::type(AdvancedSettingsDTO::class));

        Functions\expect('__')
            ->once()
            ->with('Settings Saved!', 'em-smtp-relay')
            ->andReturn('Settings Saved!');

        $this->notifier
            ->shouldReceive('addSuccess')
            ->with('Settings Saved!')
            ->once();

        $tab = new AdvancedTab(
            $this->validator,
            $this->nonceManager,
            $this->config,
            $this->request,
            $this->notifier
        );

        $reflection = new \ReflectionClass($tab);
        $method = $reflection->getMethod('handleFormSubmission');
        $method->setAccessible(true);
        $method->invoke($tab);
    }

    public function testHandleFormSubmissionCreatesDTOCorrectly(): void
    {
        $requestData = [
            'em_smtp_relay_update_advanced_settings' => '1',
            'em_smtp_relay_reply_to_email' => 'reply@example.com',
            'em_smtp_relay_reply_to_name' => 'Reply Name',
            'em_smtp_relay_force_reply_to' => '1',
            'em_smtp_relay_cc_email' => 'cc@example.com',
            'em_smtp_relay_cc_name' => 'CC Name',
            'em_smtp_relay_force_cc' => '1',
            'em_smtp_relay_bcc_email' => 'bcc@example.com',
            'em_smtp_relay_bcc_name' => 'BCC Name',
            'em_smtp_relay_force_bcc' => '1',
        ];

        $this->request = new RequestHandler($requestData);

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $capturedDTO = null;

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->once()
            ->with(Mockery::on(function($dto) use (&$capturedDTO) {
                $capturedDTO = $dto;
                return $dto instanceof AdvancedSettingsDTO;
            }))
            ->andReturn([]);

        $this->config
            ->shouldReceive('saveAdvancedSettings')
            ->once();

        $this->notifier
            ->shouldReceive('addSuccess')
            ->once();

        $tab = new AdvancedTab(
            $this->validator,
            $this->nonceManager,
            $this->config,
            $this->request,
            $this->notifier
        );

        $reflection = new \ReflectionClass($tab);
        $method = $reflection->getMethod('handleFormSubmission');
        $method->setAccessible(true);
        $method->invoke($tab);

        $this->assertInstanceOf(AdvancedSettingsDTO::class, $capturedDTO);
        $this->assertEquals('reply@example.com', $capturedDTO->replyToEmail);
        $this->assertEquals('Reply Name', $capturedDTO->replyToName);
        $this->assertEquals(1, $capturedDTO->forceReplyTo);
        $this->assertEquals('cc@example.com', $capturedDTO->ccEmail);
        $this->assertEquals('CC Name', $capturedDTO->ccName);
        $this->assertEquals(1, $capturedDTO->forceCc);
        $this->assertEquals('bcc@example.com', $capturedDTO->bccEmail);
        $this->assertEquals('BCC Name', $capturedDTO->bccName);
        $this->assertEquals(1, $capturedDTO->forceBcc);
    }

    public function testHandleFormSubmissionWithMultipleValidationErrors(): void
    {
        $requestData = [
            'em_smtp_relay_update_advanced_settings' => '1',
            'em_smtp_relay_reply_to_email' => 'invalid-email',
            'em_smtp_relay_cc_email' => 'invalid-cc',
            'em_smtp_relay_bcc_email' => 'invalid-bcc',
        ];

        $this->request = new RequestHandler($requestData);

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $errors = [
            'Invalid Reply-To Email',
            'Invalid CC Email',
            'Invalid BCC Email'
        ];

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->once()
            ->andReturn($errors);

        $this->notifier
            ->shouldReceive('addErrors')
            ->with($errors)
            ->once();

        $this->config->shouldNotReceive('saveAdvancedSettings');

        $tab = new AdvancedTab(
            $this->validator,
            $this->nonceManager,
            $this->config,
            $this->request,
            $this->notifier
        );

        $reflection = new \ReflectionClass($tab);
        $method = $reflection->getMethod('handleFormSubmission');
        $method->setAccessible(true);
        $method->invoke($tab);
    }

    public function testHandleFormSubmissionWithEmptyValues(): void
    {
        $requestData = [
            'em_smtp_relay_update_advanced_settings' => '1',
        ];

        $this->request = new RequestHandler($requestData);

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->once()
            ->with(Mockery::on(function($dto) {
                return $dto->replyToEmail === ''
                    && $dto->ccEmail === ''
                    && $dto->bccEmail === '';
            }))
            ->andReturn([]);

        $this->config
            ->shouldReceive('saveAdvancedSettings')
            ->once();

        $this->notifier
            ->shouldReceive('addSuccess')
            ->once();

        $tab = new AdvancedTab(
            $this->validator,
            $this->nonceManager,
            $this->config,
            $this->request,
            $this->notifier
        );

        $reflection = new \ReflectionClass($tab);
        $method = $reflection->getMethod('handleFormSubmission');
        $method->setAccessible(true);
        $method->invoke($tab);
    }

    protected function tearDown(): void
    {
        $templatePath = EM_SMTP_PATH . 'templates/admin/advanced-tab.php';
        if (file_exists($templatePath)) {
            @unlink($templatePath);
        }

        $templatesDir = EM_SMTP_PATH . 'templates/admin';
        if (is_dir($templatesDir)) {
            @rmdir($templatesDir);
        }

        $templatesRoot = EM_SMTP_PATH . 'templates';
        if (is_dir($templatesRoot)) {
            @rmdir($templatesRoot);
        }

        parent::tearDown();
    }
}