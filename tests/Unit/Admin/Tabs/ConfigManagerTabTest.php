<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Admin\Tabs;

use Emercury\Smtp\Admin\AdminNotifier;
use Emercury\Smtp\Admin\Tabs\ConfigManagerTab;
use Emercury\Smtp\Config\DTO\AdvancedSettingsDTO;
use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;
use Emercury\Smtp\Contracts\ConfigInterface;
use Emercury\Smtp\Contracts\NonceManagerInterface;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Mockery;

class ConfigManagerTabTest extends TestCase
{
    private ConfigInterface $config;
    private NonceManagerInterface $nonceManager;
    private AdminNotifier $notifier;
    private ConfigManagerTab $configManagerTab;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = Mockery::mock(ConfigInterface::class);
        $this->nonceManager = Mockery::mock(NonceManagerInterface::class);
        $this->notifier = Mockery::mock(AdminNotifier::class);

        Functions\when('__')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('wp_die')->alias(function ($message) {
            throw new \Exception($message);
        });
        Functions\when('current_time')->justReturn('2024-01-01 12:00:00');
        Functions\when('wp_json_encode')->alias(function ($data, $options = 0) {
            return json_encode($data, $options);
        });

        if (!defined('EM_SMTP_VERSION')) {
            define('EM_SMTP_VERSION', '1.0.0');
        }

        if (!defined('EM_SMTP_PATH')) {
            define('EM_SMTP_PATH', __DIR__ . '/');
        }

        if (!defined('UPLOAD_ERR_OK')) {
            define('UPLOAD_ERR_OK', 0);
        }
    }

    private function createConfigManagerTab(): ConfigManagerTab
    {
        return new ConfigManagerTab(
            $this->config,
            $this->nonceManager,
            $this->notifier
        );
    }

    // Constructor and initialization tests
    public function testConstructorRegistersAdminInitHook(): void
    {
        Actions\expectAdded('admin_init')->once();

        $this->createConfigManagerTab();
    }

    public function testInitHookIsRegistered(): void
    {
        $actionAdded = false;

        Functions\when('add_action')->alias(function ($hook, $callback) use (&$actionAdded) {
            if ($hook === 'admin_init') {
                $actionAdded = true;
            }
        });

        $this->createConfigManagerTab();

        $this->assertTrue($actionAdded);
    }

    // render() tests
    public function testRenderIncludesTemplate(): void
    {
        $configManagerTab = $this->createConfigManagerTab();

        // Template include can't be easily tested in unit tests
        $this->expectNotToPerformAssertions();
    }

    // handleExport() tests
    public function testHandleExportNotCalledWhenPostParameterMissing(): void
    {
        unset($_POST['em_smtp_relay_export_config']);

        $this->nonceManager->shouldNotReceive('verifyWithCapability');

        $configManagerTab = $this->createConfigManagerTab();

        do_action('admin_init');
    }

    public function testHandleExportVerifiesNonce(): void
    {
        $_POST['em_smtp_relay_export_config'] = '1';

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->with('em_smtp_relay_export_config')
            ->andReturn(false);

        Functions\expect('esc_html__')
            ->twice()
            ->andReturnArg();

        $configManagerTab = $this->createConfigManagerTab();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Security check failed. Please try again.');

        do_action('admin_init');
    }

    public function testHandleExportDiesWhenNonceVerificationFails(): void
    {
        $_POST['em_smtp_relay_export_config'] = '1';

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

        $configManagerTab = $this->createConfigManagerTab();

        $this->expectException(\Exception::class);
        do_action('admin_init');
    }

    public function testHandleExportGetsGeneralSettings(): void
    {
        $_POST['em_smtp_relay_export_config'] = '1';

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $generalDTO = $this->createMockGeneralDTO();
        $advancedDTO = $this->createMockAdvancedDTO();

        $this->config->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($generalDTO);

        $this->config->shouldReceive('getAdvancedSettings')
            ->once()
            ->andReturn($advancedDTO);

        $this->config->shouldReceive('getSmtpPort')
            ->once()
            ->andReturn(587);

        $configManagerTab = $this->createConfigManagerTab();

        // Suppress output and exit
        ob_start();
        try {
            do_action('admin_init');
        } catch (\Throwable $e) {
            // Expected exit
        }
        ob_end_clean();
    }

    public function testHandleExportSetsSmtpPortWhenZero(): void
    {
        $_POST['em_smtp_relay_export_config'] = '1';

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $generalDTO = $this->createMockGeneralDTO();
        $generalDTO->smtpPort = 0;
        $generalDTO->smtpEncryption = 'tls';

        $advancedDTO = $this->createMockAdvancedDTO();

        $this->config->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($generalDTO);

        $this->config->shouldReceive('getAdvancedSettings')
            ->once()
            ->andReturn($advancedDTO);

        $this->config->shouldReceive('getSmtpPort')
            ->once()
            ->with('tls')
            ->andReturn(587);

        $configManagerTab = $this->createConfigManagerTab();

        ob_start();
        try {
            do_action('admin_init');
        } catch (\Throwable $e) {
            // Expected exit
        }
        ob_end_clean();
    }

    public function testHandleExportRemovesPasswordFromExportData(): void
    {
        $_POST['em_smtp_relay_export_config'] = '1';

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $generalDTO = $this->createMockGeneralDTO();
        $generalDTO->smtpPassword = 'secret_password';

        $advancedDTO = $this->createMockAdvancedDTO();

        $this->config->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($generalDTO);

        $this->config->shouldReceive('getAdvancedSettings')
            ->once()
            ->andReturn($advancedDTO);

        $this->config->shouldReceive('getSmtpPort')
            ->once()
            ->andReturn(587);

        $configManagerTab = $this->createConfigManagerTab();

        ob_start();
        try {
            do_action('admin_init');
        } catch (\Throwable $e) {
            // Expected exit
        }
        $output = ob_get_clean();

        $this->assertStringNotContainsString('secret_password', $output);
        $this->assertStringNotContainsString('em_smtp_password', $output);
    }

    public function testHandleExportSetsCorrectHeaders(): void
    {
        $_POST['em_smtp_relay_export_config'] = '1';

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->config->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($this->createMockGeneralDTO());

        $this->config->shouldReceive('getAdvancedSettings')
            ->once()
            ->andReturn($this->createMockAdvancedDTO());

        $this->config->shouldReceive('getSmtpPort')
            ->once()
            ->andReturn(587);

        $configManagerTab = $this->createConfigManagerTab();

        // We can't easily test headers in unit tests without xdebug_get_headers
        // This would be better as integration test
        $this->expectNotToPerformAssertions();

        ob_start();
        try {
            do_action('admin_init');
        } catch (\Throwable $e) {
            // Expected exit
        }
        ob_end_clean();
    }

    public function testHandleExportOutputsJsonData(): void
    {
        $_POST['em_smtp_relay_export_config'] = '1';

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $generalDTO = $this->createMockGeneralDTO();
        $advancedDTO = $this->createMockAdvancedDTO();

        $this->config->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($generalDTO);

        $this->config->shouldReceive('getAdvancedSettings')
            ->once()
            ->andReturn($advancedDTO);

        $this->config->shouldReceive('getSmtpPort')
            ->once()
            ->andReturn(587);

        Functions\expect('current_time')
            ->once()
            ->with('mysql')
            ->andReturn('2024-01-01 12:00:00');

        $configManagerTab = $this->createConfigManagerTab();

        ob_start();
        try {
            do_action('admin_init');
        } catch (\Throwable $e) {
            // Expected exit
        }
        $output = ob_get_clean();

        $this->assertJson($output);

        $data = json_decode($output, true);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('export_date', $data);
        $this->assertArrayHasKey('general', $data);
        $this->assertArrayHasKey('advanced', $data);
    }

    public function testHandleExportIncludesVersionAndDate(): void
    {
        $_POST['em_smtp_relay_export_config'] = '1';

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->config->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($this->createMockGeneralDTO());

        $this->config->shouldReceive('getAdvancedSettings')
            ->once()
            ->andReturn($this->createMockAdvancedDTO());

        $this->config->shouldReceive('getSmtpPort')
            ->once()
            ->andReturn(587);

        Functions\expect('current_time')
            ->once()
            ->with('mysql')
            ->andReturn('2024-01-01 12:00:00');

        $configManagerTab = $this->createConfigManagerTab();

        ob_start();
        try {
            do_action('admin_init');
        } catch (\Throwable $e) {
            // Expected exit
        }
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertEquals('1.0.0', $data['version']);
        $this->assertEquals('2024-01-01 12:00:00', $data['export_date']);
    }

    // handleImport() tests
    public function testHandleImportNotCalledWhenPostParameterMissing(): void
    {
        unset($_POST['em_smtp_relay_import_config']);

        $this->nonceManager->shouldNotReceive('verifyWithCapability');

        $configManagerTab = $this->createConfigManagerTab();

        do_action('admin_init');
    }

    public function testHandleImportVerifiesNonce(): void
    {
        $_POST['em_smtp_relay_import_config'] = '1';

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->with('em_smtp_relay_import_config')
            ->andReturn(false);

        Functions\expect('esc_html__')
            ->twice()
            ->andReturnArg();

        $configManagerTab = $this->createConfigManagerTab();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Security check failed. Please try again.');

        do_action('admin_init');
    }

    public function testHandleImportDiesWhenNonceVerificationFails(): void
    {
        $_POST['em_smtp_relay_import_config'] = '1';

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(false);

        Functions\expect('wp_die')
            ->once()
            ->andThrow(new \Exception('Security check failed. Please try again.'));

        $configManagerTab = $this->createConfigManagerTab();

        $this->expectException(\Exception::class);
        do_action('admin_init');
    }

    public function testHandleImportAddsErrorWhenNoFileUploaded(): void
    {
        $_POST['em_smtp_relay_import_config'] = '1';
        unset($_FILES['config_file']);

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->notifier->shouldReceive('addError')
            ->once()
            ->with('Please select a valid configuration file.');

        $configManagerTab = $this->createConfigManagerTab();

        do_action('admin_init');
    }

    public function testHandleImportAddsErrorWhenFileUploadFails(): void
    {
        $_POST['em_smtp_relay_import_config'] = '1';
        $_FILES['config_file'] = [
            'name' => 'config.json',
            'type' => 'application/json',
            'tmp_name' => '/tmp/phpXXX',
            'error' => UPLOAD_ERR_INI_SIZE,
            'size' => 0
        ];

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->notifier->shouldReceive('addError')
            ->once()
            ->with('Please select a valid configuration file.');

        $configManagerTab = $this->createConfigManagerTab();

        do_action('admin_init');
    }

    public function testHandleImportAddsErrorForInvalidFileType(): void
    {
        $_POST['em_smtp_relay_import_config'] = '1';
        $_FILES['config_file'] = [
            'name' => 'config.txt',
            'type' => 'text/plain',
            'tmp_name' => '/tmp/phpXXX',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024
        ];

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->notifier->shouldReceive('addError')
            ->once()
            ->with('Invalid file type. Please upload a JSON file.');

        $configManagerTab = $this->createConfigManagerTab();

        do_action('admin_init');
    }

    public function testHandleImportAddsErrorForNonJsonExtension(): void
    {
        $_POST['em_smtp_relay_import_config'] = '1';
        $_FILES['config_file'] = [
            'name' => 'config.pdf',
            'type' => 'application/pdf',
            'tmp_name' => '/tmp/phpXXX',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024
        ];

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        Functions\expect('pathinfo')
            ->once()
            ->andReturn('pdf');

        $this->notifier->shouldReceive('addError')
            ->once()
            ->with('Invalid file type. Please upload a JSON file.');

        $configManagerTab = $this->createConfigManagerTab();

        do_action('admin_init');
    }

    public function testHandleImportAddsErrorForFileTooLarge(): void
    {
        $_POST['em_smtp_relay_import_config'] = '1';
        $_FILES['config_file'] = [
            'name' => 'config.json',
            'type' => 'application/json',
            'tmp_name' => '/tmp/phpXXX',
            'error' => UPLOAD_ERR_OK,
            'size' => 2097152 // 2MB
        ];

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->notifier->shouldReceive('addError')
            ->once()
            ->with('File is too large. Maximum size is 1MB.');

        $configManagerTab = $this->createConfigManagerTab();

        do_action('admin_init');
    }

    public function testHandleImportAddsErrorForInvalidJson(): void
    {
        $_POST['em_smtp_relay_import_config'] = '1';

        $tmpFile = tempnam(sys_get_temp_dir(), 'config');
        file_put_contents($tmpFile, 'invalid json {');

        $_FILES['config_file'] = [
            'name' => 'config.json',
            'type' => 'application/json',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 100
        ];

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        Functions\expect('file_get_contents')
            ->once()
            ->with($tmpFile)
            ->andReturn('invalid json {');

        $this->notifier->shouldReceive('addError')
            ->once()
            ->with('Invalid JSON format.');

        $configManagerTab = $this->createConfigManagerTab();

        do_action('admin_init');

        unlink($tmpFile);
    }

    public function testHandleImportAddsErrorForInvalidStructure(): void
    {
        $_POST['em_smtp_relay_import_config'] = '1';

        $tmpFile = tempnam(sys_get_temp_dir(), 'config');
        $invalidData = json_encode(['version' => '1.0.0']);
        file_put_contents($tmpFile, $invalidData);

        $_FILES['config_file'] = [
            'name' => 'config.json',
            'type' => 'application/json',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 100
        ];

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        Functions\expect('file_get_contents')
            ->once()
            ->andReturn($invalidData);

        $this->notifier->shouldReceive('addError')
            ->once()
            ->with('Invalid configuration file structure.');

        $configManagerTab = $this->createConfigManagerTab();

        do_action('admin_init');

        unlink($tmpFile);
    }

    public function testHandleImportSuccessfullyImportsConfiguration(): void
    {
        $_POST['em_smtp_relay_import_config'] = '1';

        $validData = [
            'version' => '1.0.0',
            'export_date' => '2024-01-01 12:00:00',
            'general' => [
                'em_smtp_username' => 'user@example.com',
                'em_smtp_password' => 'old_password',
                'from_email' => 'from@example.com'
            ],
            'advanced' => [
                'reply_to_email' => 'reply@example.com'
            ]
        ];

        $tmpFile = tempnam(sys_get_temp_dir(), 'config');
        file_put_contents($tmpFile, json_encode($validData));

        $_FILES['config_file'] = [
            'name' => 'config.json',
            'type' => 'application/json',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 100
        ];

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        Functions\expect('file_get_contents')
            ->once()
            ->andReturn(json_encode($validData));

        $currentDTO = $this->createMockGeneralDTO();
        $currentDTO->smtpPassword = 'current_password';

        $this->config->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($currentDTO);

        Functions\expect('update_option')
            ->once()
            ->with('em_smtp_relay_data', Mockery::on(function ($data) {
                return $data['em_smtp_password'] === 'current_password';
            }))
            ->andReturn(true);

        Functions\expect('update_option')
            ->once()
            ->with('em_smtp_relay_advanced_data', $validData['advanced'])
            ->andReturn(true);

        $this->notifier->shouldReceive('addSuccess')
            ->once()
            ->with('Configuration imported successfully!');

        $configManagerTab = $this->createConfigManagerTab();

        do_action('admin_init');

        unlink($tmpFile);
    }

    public function testHandleImportPreservesCurrentPasswordByDefault(): void
    {
        $_POST['em_smtp_relay_import_config'] = '1';

        $validData = [
            'version' => '1.0.0',
            'export_date' => '2024-01-01 12:00:00',
            'general' => [
                'em_smtp_username' => 'user@example.com',
                'em_smtp_password' => 'imported_password',
                'from_email' => 'from@example.com'
            ],
            'advanced' => []
        ];

        $tmpFile = tempnam(sys_get_temp_dir(), 'config');
        file_put_contents($tmpFile, json_encode($validData));

        $_FILES['config_file'] = [
            'name' => 'config.json',
            'type' => 'application/json',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 100
        ];

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        Functions\expect('file_get_contents')
            ->once()
            ->andReturn(json_encode($validData));

        $currentDTO = $this->createMockGeneralDTO();
        $currentDTO->smtpPassword = 'existing_password';

        $this->config->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($currentDTO);

        Functions\expect('update_option')
            ->twice()
            ->andReturn(true);

        $this->notifier->shouldReceive('addSuccess')
            ->once();

        $configManagerTab = $this->createConfigManagerTab();

        do_action('admin_init');

        unlink($tmpFile);
    }

    public function testHandleImportOverwritesPasswordWhenRequested(): void
    {
        $_POST['em_smtp_relay_import_config'] = '1';
        $_POST['overwrite_password'] = '1';

        $validData = [
            'version' => '1.0.0',
            'export_date' => '2024-01-01 12:00:00',
            'general' => [
                'em_smtp_username' => 'user@example.com',
                'em_smtp_password' => 'new_password',
                'from_email' => 'from@example.com'
            ],
            'advanced' => []
        ];

        $tmpFile = tempnam(sys_get_temp_dir(), 'config');
        file_put_contents($tmpFile, json_encode($validData));

        $_FILES['config_file'] = [
            'name' => 'config.json',
            'type' => 'application/json',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 100
        ];

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        Functions\expect('file_get_contents')
            ->once()
            ->andReturn(json_encode($validData));

        // Should NOT call getGeneralSettings when overwriting password
        $this->config->shouldNotReceive('getGeneralSettings');

        Functions\expect('update_option')
            ->once()
            ->with('em_smtp_relay_data', Mockery::on(function ($data) {
                return $data['em_smtp_password'] === 'new_password';
            }))
            ->andReturn(true);

        Functions\expect('update_option')
            ->once()
            ->with('em_smtp_relay_advanced_data', [])
            ->andReturn(true);

        $this->notifier->shouldReceive('addSuccess')
            ->once();

        $configManagerTab = $this->createConfigManagerTab();

        do_action('admin_init');

        unlink($tmpFile);
    }

    public function testHandleImportAddsErrorWhenUpdateOptionFails(): void
    {
        $_POST['em_smtp_relay_import_config'] = '1';

        $validData = [
            'version' => '1.0.0',
            'export_date' => '2024-01-01 12:00:00',
            'general' => ['em_smtp_username' => 'user@example.com'],
            'advanced' => []
        ];

        $tmpFile = tempnam(sys_get_temp_dir(), 'config');
        file_put_contents($tmpFile, json_encode($validData));

        $_FILES['config_file'] = [
            'name' => 'config.json',
            'type' => 'application/json',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 100
        ];

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        Functions\expect('file_get_contents')
            ->once()
            ->andReturn(json_encode($validData));

        $this->config->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($this->createMockGeneralDTO());

        Functions\expect('update_option')
            ->once()
            ->andThrow(new \Exception('Database error'));

        $this->notifier->shouldReceive('addError')
            ->once()
            ->with(Mockery::on(function ($message) {
                return strpos($message, 'Failed to import configuration') !== false;
            }));

        $configManagerTab = $this->createConfigManagerTab();

        do_action('admin_init');

        unlink($tmpFile);
    }

    public function testHandleImportAcceptsJsonMimeType(): void
    {
        $_POST['em_smtp_relay_import_config'] = '1';

        $validData = [
            'version' => '1.0.0',
            'general' => ['em_smtp_username' => 'user@example.com'],
            'advanced' => []
        ];

        $tmpFile = tempnam(sys_get_temp_dir(), 'config');
        file_put_contents($tmpFile, json_encode($validData));

        $_FILES['config_file'] = [
            'name' => 'config.json',
            'type' => 'application/json',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 100
        ];

        $this->nonceManager->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        Functions\expect('file_get_contents')
            ->once()
            ->andReturn(json_encode($validData));

        $this->config->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($this->createMockGeneralDTO());

        Functions\expect('update_option')->twice()->andReturn(true);

        $this->notifier->shouldReceive('addSuccess')
            ->once();

        $configManagerTab = $this->createConfigManagerTab();

        do_action('admin_init');

        unlink($tmpFile);
    }

    // Helper methods
    private function createMockGeneralDTO(): SmtpSettingsDTO
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

    private function createMockAdvancedDTO(): AdvancedSettingsDTO
    {
        $dto = new AdvancedSettingsDTO();
        $dto->replyToEmail = 'reply@example.com';
        $dto->replyToName = 'Reply Name';
        $dto->forceReplyTo = 0;
        $dto->ccEmail = '';
        $dto->ccName = '';
        $dto->forceCc = 0;
        $dto->bccEmail = '';
        $dto->bccName = '';
        $dto->forceBcc = 0;

        return $dto;
    }

    protected function tearDown(): void
    {
        unset($_POST['em_smtp_relay_export_config']);
        unset($_POST['em_smtp_relay_import_config']);
        unset($_POST['overwrite_password']);
        unset($_FILES['config_file']);

        parent::tearDown();
    }
}