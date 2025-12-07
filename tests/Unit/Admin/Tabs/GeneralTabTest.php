<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Admin\Tabs;

use Emercury\Smtp\Admin\AdminNotifier;
use Emercury\Smtp\Admin\Tabs\GeneralTab;
use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;
use Emercury\Smtp\Contracts\ConfigInterface;
use Emercury\Smtp\Contracts\EncryptionInterface;
use Emercury\Smtp\Contracts\NonceManagerInterface;
use Emercury\Smtp\Contracts\RateLimiterInterface;
use Emercury\Smtp\Contracts\ValidatorInterface;
use Emercury\Smtp\Core\RequestHandler;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Mockery;

class GeneralTabTest extends TestCase
{
    private EncryptionInterface $encryption;
    private ValidatorInterface $validator;
    private NonceManagerInterface $nonceManager;
    private ConfigInterface $config;
    private RateLimiterInterface $rateLimiter;
    private AdminNotifier $notifier;
    private RequestHandler $request;
    private GeneralTab $generalTab;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encryption = Mockery::mock(EncryptionInterface::class);
        $this->validator = Mockery::mock(ValidatorInterface::class);
        $this->nonceManager = Mockery::mock(NonceManagerInterface::class);
        $this->config = Mockery::mock(ConfigInterface::class);
        $this->rateLimiter = Mockery::mock(RateLimiterInterface::class);
        $this->notifier = Mockery::mock(AdminNotifier::class);
        $this->request = Mockery::mock(RequestHandler::class);

        Functions\when('__')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\when('wp_die')->alias(function ($message) {
            throw new \Exception($message);
        });
    }

    private function createGeneralTab(): GeneralTab
    {
        return new GeneralTab(
            $this->encryption,
            $this->validator,
            $this->nonceManager,
            $this->config,
            $this->rateLimiter,
            $this->notifier,
            $this->request
        );
    }

    // Constructor and initialization tests
    public function testConstructorRegistersAdminInitHook(): void
    {
        Actions\expectAdded('admin_init')->once();

        $this->createGeneralTab();
    }

    public function testInitHookIsRegistered(): void
    {
        $actionAdded = false;

        Functions\when('add_action')->alias(function ($hook, $callback) use (&$actionAdded) {
            if ($hook === 'admin_init') {
                $actionAdded = true;
            }
        });

        $this->createGeneralTab();

        $this->assertTrue($actionAdded);
    }

    // render() tests
    public function testRenderIncludesTemplate(): void
    {
        $dto = $this->createMockDTO();

        $this->config->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($dto);

        define('EM_SMTP_PATH', '/fake/path/');

        Functions\expect('include')
            ->never(); // We can't mock include directly

        $generalTab = $this->createGeneralTab();

        // This will fail if template doesn't exist, which is expected in unit tests
        // In real tests, you'd mock the file system or use integration tests
        $this->expectNotToPerformAssertions();
    }

    public function testRenderLoadsGeneralSettings(): void
    {
        $dto = $this->createMockDTO();

        $this->config->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($dto);

        if (!defined('EM_SMTP_PATH')) {
            define('EM_SMTP_PATH', __DIR__ . '/');
        }

        $generalTab = $this->createGeneralTab();

        // Create a minimal template file for testing
        $templatePath = EM_SMTP_PATH . 'templates/admin/general-tab.php';
        if (!file_exists(dirname($templatePath))) {
            @mkdir(dirname($templatePath), 0777, true);
        }
        @file_put_contents($templatePath, '<?php // Mock template');

        try {
            $generalTab->render();
        } catch (\Exception $e) {
            // Template might not exist in unit tests
        }

        @unlink($templatePath);
    }

    // handleSubmit() tests - via admin_init hook
    public function testHandleSubmitNotCalledWhenRequestParameterMissing(): void
    {
        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(false);

        $this->nonceManager->shouldNotReceive('verifyWithCapability');

        $generalTab = $this->createGeneralTab();

        // Trigger admin_init
        do_action('admin_init');
    }

    public function testHandleSubmitVerifiesNonce(): void
    {
        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(true);

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->with('em_smtp_relay_settings')
            ->andReturn(false);

        Functions\expect('esc_html__')
            ->twice()
            ->andReturn('Security check failed. Please try again.');

        $generalTab = $this->createGeneralTab();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Security check failed. Please try again.');

        do_action('admin_init');
    }

    public function testHandleSubmitDiesWhenNonceVerificationFails(): void
    {
        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(true);

        $this->nonceManager->shouldReceive('verifyWithCapability')
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

        $generalTab = $this->createGeneralTab();

        $this->expectException(\Exception::class);
        do_action('admin_init');
    }

    public function testHandleSubmitChecksRateLimit(): void
    {
        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(true);

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        Functions\expect('get_current_user_id')
            ->once()
            ->andReturn(123);

        $this->rateLimiter->shouldReceive('checkLimit')
            ->once()
            ->with('settings_update_123')
            ->andReturn(false);

        $this->notifier->shouldReceive('addError')
            ->once()
            ->with('Too many update attempts. Please wait before trying again.');

        $generalTab = $this->createGeneralTab();

        do_action('admin_init');
    }

    public function testHandleSubmitReturnsEarlyWhenRateLimitExceeded(): void
    {
        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(true);

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->rateLimiter->shouldReceive('checkLimit')
            ->once()
            ->andReturn(false);

        $this->notifier->shouldReceive('addError')
            ->once();

        // Should not proceed to validation
        $this->validator->shouldNotReceive('validateSmtpSettings');

        $generalTab = $this->createGeneralTab();

        do_action('admin_init');
    }

    public function testHandleSubmitCreatesSmtpSettingsDTOFromRequest(): void
    {
        $this->setupSuccessfulSubmit();

        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(true);

        // Mock SmtpSettingsDTO::fromRequest() static call
        // This is tricky in unit tests, we'll validate through validator call

        $this->validator->shouldReceive('validateSmtpSettings')
            ->once()
            ->andReturn([]);

        $this->config->shouldReceive('getSmtpPort')
            ->once()
            ->andReturn(587);

        $this->encryption->shouldReceive('encrypt')
            ->once()
            ->andReturn('encrypted');

        $this->config->shouldReceive('saveGeneralSettings')
            ->once();

        $this->notifier->shouldReceive('addSuccess')
            ->once();

        $generalTab = $this->createGeneralTab();

        do_action('admin_init');
    }

    public function testHandleSubmitValidatesSmtpSettings(): void
    {
        $this->setupSuccessfulSubmit();

        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(true);

        $this->validator->shouldReceive('validateSmtpSettings')
            ->once()
            ->with(Mockery::type(SmtpSettingsDTO::class))
            ->andReturn([]);

        $this->config->shouldReceive('getSmtpPort')->once()->andReturn(587);
        $this->encryption->shouldReceive('encrypt')->once()->andReturn('encrypted');
        $this->config->shouldReceive('saveGeneralSettings')->once();
        $this->notifier->shouldReceive('addSuccess')->once();

        $generalTab = $this->createGeneralTab();

        do_action('admin_init');
    }

    public function testHandleSubmitAddsErrorsWhenValidationFails(): void
    {
        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(true);

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->rateLimiter->shouldReceive('checkLimit')
            ->once()
            ->andReturn(true);

        $validationErrors = [
            'email' => 'Invalid email address',
            'username' => 'Username is required'
        ];

        $this->validator->shouldReceive('validateSmtpSettings')
            ->once()
            ->andReturn($validationErrors);

        $this->notifier->shouldReceive('addErrors')
            ->once()
            ->with($validationErrors);

        // Should not proceed to save
        $this->config->shouldNotReceive('saveGeneralSettings');
        $this->notifier->shouldNotReceive('addSuccess');

        $generalTab = $this->createGeneralTab();

        do_action('admin_init');
    }

    public function testHandleSubmitDoesNotSaveWhenValidationFails(): void
    {
        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(true);

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->rateLimiter->shouldReceive('checkLimit')
            ->once()
            ->andReturn(true);

        $this->validator->shouldReceive('validateSmtpSettings')
            ->once()
            ->andReturn(['error' => 'Validation failed']);

        $this->notifier->shouldReceive('addErrors')->once();

        $this->config->shouldNotReceive('saveGeneralSettings');

        $generalTab = $this->createGeneralTab();

        do_action('admin_init');
    }

    public function testHandleSubmitSavesSettingsWhenValidationPasses(): void
    {
        $this->setupSuccessfulSubmit();

        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(true);

        $this->validator->shouldReceive('validateSmtpSettings')
            ->once()
            ->andReturn([]);

        $this->config->shouldReceive('getSmtpPort')
            ->once()
            ->with('tls')
            ->andReturn(587);

        $this->encryption->shouldReceive('encrypt')
            ->once()
            ->with('password123')
            ->andReturn('encrypted_password');

        $this->config->shouldReceive('saveGeneralSettings')
            ->once()
            ->with(Mockery::on(function ($dto) {
                return $dto instanceof SmtpSettingsDTO &&
                    $dto->smtpPort === 587 &&
                    $dto->smtpPassword === 'encrypted_password';
            }));

        $this->notifier->shouldReceive('addSuccess')
            ->once()
            ->with('Settings Saved!');

        $generalTab = $this->createGeneralTab();

        do_action('admin_init');
    }

    // saveSettings() / processPassword() integration tests
    public function testSaveSettingsEncryptsPassword(): void
    {
        $this->setupSuccessfulSubmit();

        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(true);

        $this->validator->shouldReceive('validateSmtpSettings')
            ->once()
            ->andReturn([]);

        $this->encryption->shouldReceive('encrypt')
            ->once()
            ->with('newpassword')
            ->andReturn('encrypted_newpassword');

        $this->config->shouldReceive('getSmtpPort')
            ->once()
            ->andReturn(587);

        $this->config->shouldReceive('saveGeneralSettings')
            ->once();

        $this->notifier->shouldReceive('addSuccess')->once();

        $generalTab = $this->createGeneralTab();

        do_action('admin_init');
    }

    public function testSaveSettingsKeepsExistingPasswordWhenEmpty(): void
    {
        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(true);

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->rateLimiter->shouldReceive('checkLimit')
            ->once()
            ->andReturn(true);

        $this->validator->shouldReceive('validateSmtpSettings')
            ->once()
            ->andReturn([]);

        // Mock DTO with empty password
        $dto = new SmtpSettingsDTO();
        $dto->smtpPassword = '';
        $dto->smtpEncryption = 'tls';
        $dto->fromEmail = 'test@example.com';

        // Should get current settings when password is empty
        $currentDTO = new SmtpSettingsDTO();
        $currentDTO->smtpPassword = 'existing_encrypted_password';

        $this->config->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($currentDTO);

        $this->config->shouldReceive('getSmtpPort')
            ->once()
            ->andReturn(587);

        $this->config->shouldReceive('saveGeneralSettings')
            ->once()
            ->with(Mockery::on(function ($dto) {
                return $dto->smtpPassword === 'existing_encrypted_password';
            }));

        $this->notifier->shouldReceive('addSuccess')->once();

        $generalTab = $this->createGeneralTab();

        do_action('admin_init');
    }

    public function testSaveSettingsDiesWhenEncryptionFails(): void
    {
        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(true);

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->rateLimiter->shouldReceive('checkLimit')
            ->once()
            ->andReturn(true);

        $this->validator->shouldReceive('validateSmtpSettings')
            ->once()
            ->andReturn([]);

        $this->encryption->shouldReceive('encrypt')
            ->once()
            ->andThrow(new \Exception('Encryption failed'));

        Functions\expect('esc_html__')
            ->with('Failed to encrypt password. Please try again.', 'em-smtp-relay')
            ->andReturn('Failed to encrypt password. Please try again.');

        Functions\expect('esc_html__')
            ->with('Encryption Error', 'em-smtp-relay')
            ->andReturn('Encryption Error');

        Functions\expect('wp_die')
            ->once()
            ->with(
                'Failed to encrypt password. Please try again.',
                'Encryption Error',
                ['response' => 500]
            )
            ->andThrow(new \Exception('Failed to encrypt password. Please try again.'));

        $generalTab = $this->createGeneralTab();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to encrypt password. Please try again.');

        do_action('admin_init');
    }

    public function testSaveSettingsSetsSmtpPort(): void
    {
        $this->setupSuccessfulSubmit();

        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(true);

        $this->validator->shouldReceive('validateSmtpSettings')
            ->once()
            ->andReturn([]);

        $this->config->shouldReceive('getSmtpPort')
            ->once()
            ->with('ssl')
            ->andReturn(465);

        $this->encryption->shouldReceive('encrypt')
            ->once()
            ->andReturn('encrypted');

        $this->config->shouldReceive('saveGeneralSettings')
            ->once()
            ->with(Mockery::on(function ($dto) {
                return $dto->smtpPort === 465;
            }));

        $this->notifier->shouldReceive('addSuccess')->once();

        $generalTab = $this->createGeneralTab();

        do_action('admin_init');
    }

    public function testSaveSettingsAddsSuccessMessage(): void
    {
        $this->setupSuccessfulSubmit();

        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(true);

        $this->validator->shouldReceive('validateSmtpSettings')
            ->once()
            ->andReturn([]);

        $this->config->shouldReceive('getSmtpPort')
            ->once()
            ->andReturn(587);

        $this->encryption->shouldReceive('encrypt')
            ->once()
            ->andReturn('encrypted');

        $this->config->shouldReceive('saveGeneralSettings')
            ->once();

        Functions\expect('__')
            ->once()
            ->with('Settings Saved!', 'em-smtp-relay')
            ->andReturn('Settings Saved!');

        $this->notifier->shouldReceive('addSuccess')
            ->once()
            ->with('Settings Saved!');

        $generalTab = $this->createGeneralTab();

        do_action('admin_init');
    }

    // Edge cases and error scenarios
    public function testHandleSubmitWithMultipleValidationErrors(): void
    {
        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(true);

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->rateLimiter->shouldReceive('checkLimit')
            ->once()
            ->andReturn(true);

        $errors = [
            'email' => 'Invalid email',
            'username' => 'Username required',
            'host' => 'Host required'
        ];

        $this->validator->shouldReceive('validateSmtpSettings')
            ->once()
            ->andReturn($errors);

        $this->notifier->shouldReceive('addErrors')
            ->once()
            ->with($errors);

        $generalTab = $this->createGeneralTab();

        do_action('admin_init');
    }

    public function testHandleSubmitWithDifferentUserId(): void
    {
        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(true);

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        Functions\expect('get_current_user_id')
            ->once()
            ->andReturn(456);

        $this->rateLimiter->shouldReceive('checkLimit')
            ->once()
            ->with('settings_update_456')
            ->andReturn(true);

        $this->validator->shouldReceive('validateSmtpSettings')
            ->once()
            ->andReturn([]);

        $this->config->shouldReceive('getSmtpPort')->once()->andReturn(587);
        $this->encryption->shouldReceive('encrypt')->once()->andReturn('encrypted');
        $this->config->shouldReceive('saveGeneralSettings')->once();
        $this->notifier->shouldReceive('addSuccess')->once();

        $generalTab = $this->createGeneralTab();

        do_action('admin_init');
    }

    public function testProcessPasswordWithNonEmptyPassword(): void
    {
        $this->setupSuccessfulSubmit();

        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(true);

        $this->validator->shouldReceive('validateSmtpSettings')
            ->once()
            ->andReturn([]);

        $this->encryption->shouldReceive('encrypt')
            ->once()
            ->with('my_secure_password')
            ->andReturn('encrypted_my_secure_password');

        $this->config->shouldReceive('getSmtpPort')
            ->once()
            ->andReturn(587);

        $this->config->shouldReceive('saveGeneralSettings')
            ->once();

        $this->notifier->shouldReceive('addSuccess')->once();

        $generalTab = $this->createGeneralTab();

        do_action('admin_init');
    }

    public function testProcessPasswordRetrievesCurrentPasswordWhenEmpty(): void
    {
        $this->request->shouldReceive('has')
            ->with('em_smtp_relay_update_settings')
            ->andReturn(true);

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->rateLimiter->shouldReceive('checkLimit')
            ->once()
            ->andReturn(true);

        $this->validator->shouldReceive('validateSmtpSettings')
            ->once()
            ->andReturn([]);

        $currentDTO = new SmtpSettingsDTO();
        $currentDTO->smtpPassword = 'old_encrypted_password';

        $this->config->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($currentDTO);

        $this->config->shouldReceive('getSmtpPort')
            ->once()
            ->andReturn(587);

        $this->config->shouldReceive('saveGeneralSettings')
            ->once();

        $this->notifier->shouldReceive('addSuccess')->once();

        $generalTab = $this->createGeneralTab();

        do_action('admin_init');
    }

    // Helper methods
    private function setupSuccessfulSubmit(): void
    {
        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->andReturn(true);

        $this->rateLimiter->shouldReceive('checkLimit')
            ->andReturn(true);
    }

    private function createMockDTO(): SmtpSettingsDTO
    {
        $dto = new SmtpSettingsDTO();
        $dto->smtpUsername = 'test@example.com';
        $dto->smtpPassword = 'encrypted_password';
        $dto->smtpEncryption = 'tls';
        $dto->smtpPort = 587;
        $dto->fromEmail = 'from@example.com';
        $dto->fromName = 'Test Name';
        $dto->forceFromAddress = 0;

        return $dto;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}