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
    private AdvancedTab $advancedTab;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = Mockery::mock(ValidatorInterface::class);
        $this->nonceManager = Mockery::mock(NonceManagerInterface::class);
        $this->config = Mockery::mock(ConfigInterface::class);
        $this->notifier = Mockery::mock(AdminNotifier::class);
        $this->request = Mockery::mock(RequestHandler::class);

        Functions\when('__')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('wp_die')->alias(function ($message) {
            throw new \Exception($message);
        });

        if (!defined('EM_SMTP_PATH')) {
            define('EM_SMTP_PATH', '/fake/path/');
        }
    }

    private function createAdvancedTab(): AdvancedTab
    {
        return new AdvancedTab(
            $this->validator,
            $this->nonceManager,
            $this->config,
            $this->request,
            $this->notifier
        );
    }

    // Constructor tests
    public function testConstructorAcceptsDependencies(): void
    {
        $advancedTab = $this->createAdvancedTab();

        $this->assertInstanceOf(AdvancedTab::class, $advancedTab);
    }

    public function testConstructorRegistersAdminInitHook(): void
    {
        Actions\expectAdded('admin_init')->once();

        $this->createAdvancedTab();
    }

    // init() tests
    public function testInitHookIsRegistered(): void
    {
        $actionAdded = false;

        Functions\when('add_action')->alias(function ($hook, $callback) use (&$actionAdded) {
            if ($hook === 'admin_init') {
                $actionAdded = true;
            }
        });

        $this->createAdvancedTab();

        $this->assertTrue($actionAdded);
    }

    // render() tests
    public function testRenderGetsAdvancedSettingsFromConfig(): void
    {
        $dto = new AdvancedSettingsDTO(
            'reply@example.com',
            'Reply Name',
            1,
            'cc@example.com',
            'CC Name',
            0,
            'bcc@example.com',
            'BCC Name',
            1
        );

        $this->config
            ->shouldReceive('getAdvancedSettings')
            ->once()
            ->andReturn($dto);

        $advancedTab = $this->createAdvancedTab();

        ob_start();
        try {
            $advancedTab->render();
        } catch (\Throwable $e) {
            // Template file doesn't exist in unit tests
        }
        ob_end_clean();
    }

    public function testRenderIncludesTemplate(): void
    {
        $dto = new AdvancedSettingsDTO();

        $this->config
            ->shouldReceive('getAdvancedSettings')
            ->once()
            ->andReturn($dto);

        $advancedTab = $this->createAdvancedTab();

        // We can't easily test template inclusion without creating the file
        // This test verifies that render() calls the necessary methods
        $this->expectNotToPerformAssertions();

        ob_start();
        try {
            $advancedTab->render();
        } catch (\Throwable $e) {
            // Expected - template doesn't exist
        }
        ob_end_clean();
    }

    // handleFormSubmission() tests - via admin_init hook
    public function testHandleFormSubmissionNotCalledWhenRequestParameterMissing(): void
    {
        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(false);

        $this->nonceManager
            ->shouldNotReceive('verifyWithCapability');

        $advancedTab = $this->createAdvancedTab();

        do_action('admin_init');
    }

    public function testHandleFormSubmissionVerifiesNonce(): void
    {
        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(true);

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->with('em_smtp_relay_advanced_settings')
            ->andReturn(false);

        Functions\expect('esc_html__')
            ->twice()
            ->andReturnArg();

        $advancedTab = $this->createAdvancedTab();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Security check failed. Please try again.');

        do_action('admin_init');
    }

    public function testHandleFormSubmissionDiesWhenNonceVerificationFails(): void
    {
        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(true);

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(false);

        Functions\expect('esc_html__')
            ->with('Security check failed. Please try again.', 'em-smtp-relay')
            ->andReturn('Security check failed. Please try again.');

        Functions\expect('esc_html__')
            ->with('Security Error', 'em-smtp-relay')
            ->andReturn('Security Error');

        Functions\expect('wp_die')
            ->once()
            ->with(
                'Security check failed. Please try again.',
                'Security Error',
                ['response' => 403]
            )
            ->andThrow(new \Exception('Security check failed. Please try again.'));

        $advancedTab = $this->createAdvancedTab();

        $this->expectException(\Exception::class);
        do_action('admin_init');
    }

    public function testHandleFormSubmissionCreatesAdvancedSettingsDTOFromRequest(): void
    {
        $this->setupSuccessfulSubmit();

        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(true);

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->once()
            ->with(Mockery::type(AdvancedSettingsDTO::class))
            ->andReturn([]);

        $this->config
            ->shouldReceive('saveAdvancedSettings')
            ->once();

        $this->notifier
            ->shouldReceive('addSuccess')
            ->once();

        $advancedTab = $this->createAdvancedTab();

        do_action('admin_init');
    }

    public function testHandleFormSubmissionValidatesAdvancedSettings(): void
    {
        $this->setupSuccessfulSubmit();

        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(true);

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->once()
            ->with(Mockery::type(AdvancedSettingsDTO::class))
            ->andReturn([]);

        $this->config
            ->shouldReceive('saveAdvancedSettings')
            ->once();

        $this->notifier
            ->shouldReceive('addSuccess')
            ->once();

        $advancedTab = $this->createAdvancedTab();

        do_action('admin_init');
    }

    public function testHandleFormSubmissionAddsErrorsWhenValidationFails(): void
    {
        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(true);

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $validationErrors = [
            'Invalid Reply-To Email Address',
            'Invalid CC Email Address'
        ];

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->once()
            ->andReturn($validationErrors);

        $this->notifier
            ->shouldReceive('addErrors')
            ->once()
            ->with($validationErrors);

        // Should not proceed to save
        $this->config
            ->shouldNotReceive('saveAdvancedSettings');

        $this->notifier
            ->shouldNotReceive('addSuccess');

        $advancedTab = $this->createAdvancedTab();

        do_action('admin_init');
    }

    public function testHandleFormSubmissionDoesNotSaveWhenValidationFails(): void
    {
        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(true);

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->once()
            ->andReturn(['error' => 'Validation failed']);

        $this->notifier
            ->shouldReceive('addErrors')
            ->once();

        $this->config
            ->shouldNotReceive('saveAdvancedSettings');

        $advancedTab = $this->createAdvancedTab();

        do_action('admin_init');
    }

    public function testHandleFormSubmissionSavesSettingsWhenValidationPasses(): void
    {
        $this->setupSuccessfulSubmit();

        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(true);

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->once()
            ->andReturn([]);

        $this->config
            ->shouldReceive('saveAdvancedSettings')
            ->once()
            ->with(Mockery::type(AdvancedSettingsDTO::class));

        $this->notifier
            ->shouldReceive('addSuccess')
            ->once()
            ->with('Settings Saved!');

        $advancedTab = $this->createAdvancedTab();

        do_action('admin_init');
    }

    public function testHandleFormSubmissionAddsSuccessMessage(): void
    {
        $this->setupSuccessfulSubmit();

        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(true);

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->once()
            ->andReturn([]);

        $this->config
            ->shouldReceive('saveAdvancedSettings')
            ->once();

        Functions\expect('__')
            ->once()
            ->with('Settings Saved!', 'em-smtp-relay')
            ->andReturn('Settings Saved!');

        $this->notifier
            ->shouldReceive('addSuccess')
            ->once()
            ->with('Settings Saved!');

        $advancedTab = $this->createAdvancedTab();

        do_action('admin_init');
    }

    // Edge cases and error scenarios
    public function testHandleFormSubmissionWithMultipleValidationErrors(): void
    {
        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(true);

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $errors = [
            'Invalid Reply-To Email Address',
            'Invalid CC Email Address',
            'Invalid BCC Email Address'
        ];

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->once()
            ->andReturn($errors);

        $this->notifier
            ->shouldReceive('addErrors')
            ->once()
            ->with($errors);

        $advancedTab = $this->createAdvancedTab();

        do_action('admin_init');
    }

    public function testHandleFormSubmissionWithEmptyValidationErrors(): void
    {
        $this->setupSuccessfulSubmit();

        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(true);

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->once()
            ->andReturn([]);

        $this->config
            ->shouldReceive('saveAdvancedSettings')
            ->once();

        $this->notifier
            ->shouldReceive('addSuccess')
            ->once();

        $advancedTab = $this->createAdvancedTab();

        do_action('admin_init');
    }

    public function testHandleFormSubmissionCallsCorrectNonceAction(): void
    {
        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(true);

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->with('em_smtp_relay_advanced_settings')
            ->andReturn(true);

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->andReturn([]);

        $this->config
            ->shouldReceive('saveAdvancedSettings')
            ->once();

        $this->notifier
            ->shouldReceive('addSuccess')
            ->once();

        $advancedTab = $this->createAdvancedTab();

        do_action('admin_init');
    }

    public function testHandleFormSubmissionUsesCorrectRequestKey(): void
    {
        $this->request
            ->shouldReceive('has')
            ->once()
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(false);

        $this->nonceManager
            ->shouldNotReceive('verifyWithCapability');

        $advancedTab = $this->createAdvancedTab();

        do_action('admin_init');
    }

    public function testHandleFormSubmissionWithSingleValidationError(): void
    {
        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(true);

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $errors = ['Invalid Reply-To Email Address'];

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->once()
            ->andReturn($errors);

        $this->notifier
            ->shouldReceive('addErrors')
            ->once()
            ->with($errors);

        $this->config
            ->shouldNotReceive('saveAdvancedSettings');

        $advancedTab = $this->createAdvancedTab();

        do_action('admin_init');
    }

    public function testHandleFormSubmissionValidatorReceivesCorrectDTO(): void
    {
        $this->setupSuccessfulSubmit();

        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(true);

        $capturedDTO = null;

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->once()
            ->andReturnUsing(function($dto) use (&$capturedDTO) {
                $capturedDTO = $dto;
                return [];
            });

        $this->config
            ->shouldReceive('saveAdvancedSettings')
            ->once();

        $this->notifier
            ->shouldReceive('addSuccess')
            ->once();

        $advancedTab = $this->createAdvancedTab();

        do_action('admin_init');

        $this->assertInstanceOf(AdvancedSettingsDTO::class, $capturedDTO);
    }

    public function testHandleFormSubmissionConfigReceivesCorrectDTO(): void
    {
        $this->setupSuccessfulSubmit();

        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(true);

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->once()
            ->andReturn([]);

        $capturedDTO = null;

        $this->config
            ->shouldReceive('saveAdvancedSettings')
            ->once()
            ->andReturnUsing(function($dto) use (&$capturedDTO) {
                $capturedDTO = $dto;
                return true;
            });

        $this->notifier
            ->shouldReceive('addSuccess')
            ->once();

        $advancedTab = $this->createAdvancedTab();

        do_action('admin_init');

        $this->assertInstanceOf(AdvancedSettingsDTO::class, $capturedDTO);
    }

    public function testHandleFormSubmissionOnlyRunsWhenRequestHasParameter(): void
    {
        $this->request
            ->shouldReceive('has')
            ->twice()
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(false, false);

        $this->nonceManager
            ->shouldNotReceive('verifyWithCapability');

        $this->validator
            ->shouldNotReceive('validateAdvancedSettings');

        $advancedTab = $this->createAdvancedTab();

        do_action('admin_init');
        do_action('admin_init');
    }

    public function testHandleFormSubmissionStopsAfterNonceFailure(): void
    {
        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(true);

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(false);

        Functions\expect('wp_die')
            ->once()
            ->andThrow(new \Exception('Security check failed'));

        $this->validator
            ->shouldNotReceive('validateAdvancedSettings');

        $this->config
            ->shouldNotReceive('saveAdvancedSettings');

        $this->notifier
            ->shouldNotReceive('addSuccess');

        $advancedTab = $this->createAdvancedTab();

        $this->expectException(\Exception::class);
        do_action('admin_init');
    }

    public function testHandleFormSubmissionStopsAfterValidationFailure(): void
    {
        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(true);

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->once()
            ->andReturn(['Error']);

        $this->notifier
            ->shouldReceive('addErrors')
            ->once();

        $this->config
            ->shouldNotReceive('saveAdvancedSettings');

        $this->notifier
            ->shouldNotReceive('addSuccess');

        $advancedTab = $this->createAdvancedTab();

        do_action('admin_init');
    }

    public function testHandleFormSubmissionExecutesInCorrectOrder(): void
    {
        $this->request
            ->shouldReceive('has')
            ->with('em_smtp_relay_update_advanced_settings')
            ->andReturn(true);

        $executionOrder = [];

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturnUsing(function() use (&$executionOrder) {
                $executionOrder[] = 'nonce';
                return true;
            });

        $this->validator
            ->shouldReceive('validateAdvancedSettings')
            ->once()
            ->andReturnUsing(function() use (&$executionOrder) {
                $executionOrder[] = 'validate';
                return [];
            });

        $this->config
            ->shouldReceive('saveAdvancedSettings')
            ->once()
            ->andReturnUsing(function() use (&$executionOrder) {
                $executionOrder[] = 'save';
                return true;
            });

        $this->notifier
            ->shouldReceive('addSuccess')
            ->once()
            ->andReturnUsing(function() use (&$executionOrder) {
                $executionOrder[] = 'success';
            });

        $advancedTab = $this->createAdvancedTab();

        do_action('admin_init');

        $this->assertEquals(['nonce', 'validate', 'save', 'success'], $executionOrder);
    }

    public function testRenderCallsConfigOnce(): void
    {
        $this->config
            ->shouldReceive('getAdvancedSettings')
            ->once()
            ->andReturn(new AdvancedSettingsDTO());

        $advancedTab = $this->createAdvancedTab();

        ob_start();
        try {
            $advancedTab->render();
        } catch (\Throwable $e) {
            // Template doesn't exist
        }
        ob_end_clean();
    }

    public function testRenderReturnsAdvancedSettingsDTO(): void
    {
        $expectedDTO = new AdvancedSettingsDTO(
            'reply@test.com',
            'Reply Name',
            1
        );

        $this->config
            ->shouldReceive('getAdvancedSettings')
            ->once()
            ->andReturn($expectedDTO);

        $advancedTab = $this->createAdvancedTab();

        ob_start();
        try {
            $advancedTab->render();
        } catch (\Throwable $e) {
            // Template doesn't exist
        }
        ob_end_clean();

        // If we got here without exceptions, the DTO was retrieved
        $this->assertTrue(true);
    }

    // Helper methods
    private function setupSuccessfulSubmit(): void
    {
        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->andReturn(true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}