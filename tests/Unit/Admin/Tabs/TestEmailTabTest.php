<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Admin\Tabs;

use Emercury\Smtp\Admin\Tabs\TestEmailTab;
use Emercury\Smtp\Admin\AdminNotifier;
use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;
use Emercury\Smtp\Contracts\ConfigInterface;
use Emercury\Smtp\Contracts\NonceManagerInterface;
use Emercury\Smtp\Contracts\RateLimiterInterface;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

class TestEmailTabTest extends TestCase
{
    private $nonceManager;
    private $config;
    private $rateLimiter;
    private $notifier;
    private TestEmailTab $testEmailTab;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->nonceManager = Mockery::mock(NonceManagerInterface::class);
        $this->config = Mockery::mock(ConfigInterface::class);
        $this->rateLimiter = Mockery::mock(RateLimiterInterface::class);
        $this->notifier = Mockery::mock(AdminNotifier::class);

        // Mock WordPress constants
        if (!defined('EM_SMTP_PATH')) {
            define('EM_SMTP_PATH', '/fake/path/');
        }

        // Mock global functions
        Functions\expect('add_action')->zeroOrMoreTimes();
        Functions\expect('get_current_user_id')->andReturn(1);
        Functions\expect('current_user_can')->andReturn(true);
        Functions\expect('wp_upload_dir')->andReturn([
            'basedir' => '/tmp/uploads',
            'baseurl' => 'http://example.com/uploads'
        ]);

        $this->testEmailTab = new TestEmailTab(
            $this->nonceManager,
            $this->config,
            $this->rateLimiter,
            $this->notifier
        );
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    public function testConstructorInitializesActionsCorrectly(): void
    {
        Functions\expect('add_action')
            ->once()
            ->with('admin_init', Mockery::type('callable'));

        new TestEmailTab(
            $this->nonceManager,
            $this->config,
            $this->rateLimiter,
            $this->notifier
        );

        $this->assertTrue(true);
    }

    public function testRenderIncludesTemplate(): void
    {
        Functions\expect('wp_upload_dir')->andReturn([
            'basedir' => '/tmp/uploads',
            'baseurl' => 'http://example.com/uploads'
        ]);

        ob_start();
        $this->testEmailTab->render();
        $output = ob_get_clean();

        $this->assertIsString($output);
    }

    public function testHandleTestEmailFailsOnInvalidNonce(): void
    {
        $_POST['em_smtp_relay_send_test_email'] = '1';
        $_POST['em_smtp_relay_to_email'] = 'test@example.com';

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->with('em_smtp_relay_test_email')
            ->andReturn(false);

        Functions\expect('wp_die')
            ->once()
            ->with(
                Mockery::type('string'),
                Mockery::type('string'),
                ['response' => 403]
            );

        Functions\expect('esc_html__')
            ->andReturnUsing(function ($text) {
                return $text;
            });

        $reflection = new \ReflectionClass($this->testEmailTab);
        $method = $reflection->getMethod('handleTestEmail');
        $method->setAccessible(true);
        $method->invoke($this->testEmailTab);
    }

    public function testHandleTestEmailFailsOnRateLimit(): void
    {
        $_POST['em_smtp_relay_send_test_email'] = '1';
        $_POST['em_smtp_relay_to_email'] = 'test@example.com';

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        Functions\expect('get_current_user_id')->andReturn(1);

        $this->rateLimiter
            ->shouldReceive('checkLimit')
            ->once()
            ->with('test_email_1')
            ->andReturn(false);

        $this->notifier
            ->shouldReceive('addError')
            ->once()
            ->with(Mockery::type('string'));

        Functions\expect('__')
            ->andReturnUsing(function ($text) {
                return $text;
            });

        $reflection = new \ReflectionClass($this->testEmailTab);
        $method = $reflection->getMethod('handleTestEmail');
        $method->setAccessible(true);
        $method->invoke($this->testEmailTab);
    }

    public function testHandleTestEmailFailsOnInvalidRecipient(): void
    {
        $_POST['em_smtp_relay_send_test_email'] = '1';
        $_POST['em_smtp_relay_to_email'] = 'invalid-email';

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->rateLimiter
            ->shouldReceive('checkLimit')
            ->once()
            ->andReturn(true);

        Functions\expect('sanitize_email')->andReturn('invalid-email');
        Functions\expect('sanitize_text_field')->andReturnUsing(function ($text) {
            return $text;
        });
        Functions\expect('wp_kses_post')->andReturnUsing(function ($text) {
            return $text;
        });
        Functions\expect('is_email')->with('invalid-email')->andReturn(false);

        $this->notifier
            ->shouldReceive('addError')
            ->once()
            ->with(Mockery::type('string'));

        Functions\expect('__')
            ->andReturnUsing(function ($text) {
                return $text;
            });

        $reflection = new \ReflectionClass($this->testEmailTab);
        $method = $reflection->getMethod('handleTestEmail');
        $method->setAccessible(true);
        $method->invoke($this->testEmailTab);
    }

    public function testHandleTestEmailFailsOnMissingSmtpConfiguration(): void
    {
        $_POST['em_smtp_relay_send_test_email'] = '1';
        $_POST['em_smtp_relay_to_email'] = 'test@example.com';

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->rateLimiter
            ->shouldReceive('checkLimit')
            ->once()
            ->andReturn(true);

        Functions\expect('sanitize_email')->andReturn('test@example.com');
        Functions\expect('sanitize_text_field')->andReturnUsing(function ($text) {
            return $text;
        });
        Functions\expect('wp_kses_post')->andReturnUsing(function ($text) {
            return $text;
        });
        Functions\expect('is_email')->with('test@example.com')->andReturn(true);

        $dto = new SmtpSettingsDTO();
        $dto->smtpUsername = '';
        $dto->smtpPassword = '';
        $dto->fromEmail = '';

        $this->config
            ->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($dto);

        $this->notifier
            ->shouldReceive('addError')
            ->once()
            ->with(Mockery::type('string'));

        Functions\expect('__')
            ->andReturnUsing(function ($text) {
                return $text;
            });

        $reflection = new \ReflectionClass($this->testEmailTab);
        $method = $reflection->getMethod('handleTestEmail');
        $method->setAccessible(true);
        $method->invoke($this->testEmailTab);
    }

    public function testHandleTestEmailSendsSuccessfully(): void
    {
        $_POST['em_smtp_relay_send_test_email'] = '1';
        $_POST['em_smtp_relay_to_email'] = 'test@example.com';
        $_POST['em_smtp_relay_email_subject'] = 'Test Subject';
        $_POST['em_smtp_relay_email_body'] = 'Test Body';

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->rateLimiter
            ->shouldReceive('checkLimit')
            ->once()
            ->andReturn(true);

        Functions\expect('sanitize_email')->andReturn('test@example.com');
        Functions\expect('sanitize_text_field')->andReturnUsing(function ($text) {
            return $text;
        });
        Functions\expect('wp_kses_post')->andReturnUsing(function ($text) {
            return $text;
        });
        Functions\expect('is_email')->with('test@example.com')->andReturn(true);

        $dto = new SmtpSettingsDTO();
        $dto->smtpUsername = 'user@example.com';
        $dto->smtpPassword = 'encrypted_password';
        $dto->fromEmail = 'from@example.com';

        $this->config
            ->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($dto);

        Functions\expect('wp_mail')
            ->once()
            ->with(
                'test@example.com',
                'Test Subject',
                'Test Body',
                [],
                []
            )
            ->andReturn(true);

        $this->notifier
            ->shouldReceive('addSuccess')
            ->once()
            ->with(Mockery::type('string'));

        Functions\expect('__')
            ->andReturnUsing(function ($text) {
                return $text;
            });

        $reflection = new \ReflectionClass($this->testEmailTab);
        $method = $reflection->getMethod('handleTestEmail');
        $method->setAccessible(true);
        $method->invoke($this->testEmailTab);
    }

    public function testHandleTestEmailFailsToSend(): void
    {
        $_POST['em_smtp_relay_send_test_email'] = '1';
        $_POST['em_smtp_relay_to_email'] = 'test@example.com';

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->rateLimiter
            ->shouldReceive('checkLimit')
            ->once()
            ->andReturn(true);

        Functions\expect('sanitize_email')->andReturn('test@example.com');
        Functions\expect('sanitize_text_field')->andReturnUsing(function ($text) {
            return $text;
        });
        Functions\expect('wp_kses_post')->andReturnUsing(function ($text) {
            return $text;
        });
        Functions\expect('is_email')->with('test@example.com')->andReturn(true);

        $dto = new SmtpSettingsDTO();
        $dto->smtpUsername = 'user@example.com';
        $dto->smtpPassword = 'encrypted_password';
        $dto->fromEmail = 'from@example.com';

        $this->config
            ->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($dto);

        Functions\expect('wp_mail')
            ->once()
            ->andReturn(false);

        $this->notifier
            ->shouldReceive('addError')
            ->once()
            ->with(Mockery::type('string'));

        Functions\expect('__')
            ->andReturnUsing(function ($text) {
                return $text;
            });

        $reflection = new \ReflectionClass($this->testEmailTab);
        $method = $reflection->getMethod('handleTestEmail');
        $method->setAccessible(true);
        $method->invoke($this->testEmailTab);
    }

    public function testValidateAttachmentAcceptsValidFile(): void
    {
        $reflection = new \ReflectionClass($this->testEmailTab);
        $method = $reflection->getMethod('validateAttachment');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->testEmailTab,
            'image/jpeg',
            1048576 // 1MB
        );

        $this->assertTrue($result);
    }

    public function testValidateAttachmentRejectsInvalidMimeType(): void
    {
        $reflection = new \ReflectionClass($this->testEmailTab);
        $method = $reflection->getMethod('validateAttachment');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->testEmailTab,
            'application/x-executable',
            1048576
        );

        $this->assertFalse($result);
    }

    public function testValidateAttachmentRejectsTooLargeFile(): void
    {
        $reflection = new \ReflectionClass($this->testEmailTab);
        $method = $reflection->getMethod('validateAttachment');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->testEmailTab,
            'image/jpeg',
            6291456 // 6MB
        );

        $this->assertFalse($result);
    }

    public function testDeleteTestAttachmentFailsWithoutPermission(): void
    {
        $_POST['filename'] = 'test.jpg';
        $_POST['nonce'] = 'valid_nonce';

        Functions\expect('check_ajax_referer')
            ->once()
            ->with('em_smtp_delete_attachment', 'nonce');

        Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(false);

        Functions\expect('wp_send_json_error')
            ->once()
            ->with(['message' => Mockery::type('string')]);

        Functions\expect('__')
            ->andReturnUsing(function ($text) {
                return $text;
            });

        $this->testEmailTab->deleteTestAttachment();
    }

    public function testDeleteTestAttachmentFailsWithInvalidFilename(): void
    {
        $_POST['filename'] = '';
        $_POST['nonce'] = 'valid_nonce';

        Functions\expect('check_ajax_referer')
            ->once()
            ->with('em_smtp_delete_attachment', 'nonce');

        Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(true);

        Functions\expect('sanitize_file_name')
            ->once()
            ->andReturn('');

        Functions\expect('wp_send_json_error')
            ->once()
            ->with(['message' => Mockery::type('string')]);

        Functions\expect('__')
            ->andReturnUsing(function ($text) {
                return $text;
            });

        $this->testEmailTab->deleteTestAttachment();
    }

    public function testGetTestAttachmentsDirCreatesDirectoryIfNotExists(): void
    {
        Functions\expect('wp_upload_dir')->andReturn([
            'basedir' => '/tmp/uploads',
            'baseurl' => 'http://example.com/uploads'
        ]);

        Functions\expect('file_exists')
            ->with('/tmp/uploads/em-smtp-test-attachments')
            ->andReturn(false);

        Functions\expect('wp_mkdir_p')
            ->once()
            ->with('/tmp/uploads/em-smtp-test-attachments')
            ->andReturn(true);

        Functions\expect('file_exists')
            ->with('/tmp/uploads/em-smtp-test-attachments/.htaccess')
            ->andReturn(false);

        Functions\expect('file_put_contents')
            ->once()
            ->with(
                '/tmp/uploads/em-smtp-test-attachments/.htaccess',
                'deny from all'
            );

        $reflection = new \ReflectionClass($this->testEmailTab);
        $method = $reflection->getMethod('getTestAttachmentsDir');
        $method->setAccessible(true);

        $result = $method->invoke($this->testEmailTab);

        $this->assertEquals('/tmp/uploads/em-smtp-test-attachments', $result);
    }

    public function testHandleTestEmailWithDebugHeader(): void
    {
        $_POST['em_smtp_relay_send_test_email'] = '1';
        $_POST['em_smtp_relay_to_email'] = 'test@example.com';
        $_POST['debug'] = '1';

        $this->nonceManager
            ->shouldReceive('verifyWithCapability')
            ->once()
            ->andReturn(true);

        $this->rateLimiter
            ->shouldReceive('checkLimit')
            ->once()
            ->andReturn(true);

        Functions\expect('sanitize_email')->andReturn('test@example.com');
        Functions\expect('sanitize_text_field')->andReturnUsing(function ($text) {
            return $text;
        });
        Functions\expect('wp_kses_post')->andReturnUsing(function ($text) {
            return $text;
        });
        Functions\expect('is_email')->with('test@example.com')->andReturn(true);

        $dto = new SmtpSettingsDTO();
        $dto->smtpUsername = 'user@example.com';
        $dto->smtpPassword = 'encrypted_password';
        $dto->fromEmail = 'from@example.com';

        $this->config
            ->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($dto);

        Functions\expect('wp_mail')
            ->once()
            ->with(
                'test@example.com',
                Mockery::any(),
                Mockery::any(),
                ['EM-SMTP-Debug: True'],
                []
            )
            ->andReturn(true);

        $this->notifier
            ->shouldReceive('addSuccess')
            ->once();

        Functions\expect('__')
            ->andReturnUsing(function ($text) {
                return $text;
            });

        $reflection = new \ReflectionClass($this->testEmailTab);
        $method = $reflection->getMethod('handleTestEmail');
        $method->setAccessible(true);
        $method->invoke($this->testEmailTab);
    }

    public function testValidateRecipientReturnsTrueForValidEmail(): void
    {
        Functions\expect('is_email')
            ->with('test@example.com')
            ->andReturn(true);

        $reflection = new \ReflectionClass($this->testEmailTab);
        $method = $reflection->getMethod('validateRecipient');
        $method->setAccessible(true);

        $result = $method->invoke($this->testEmailTab, 'test@example.com');

        $this->assertTrue($result);
    }

    public function testValidateRecipientReturnsFalseForInvalidEmail(): void
    {
        Functions\expect('is_email')
            ->with('invalid-email')
            ->andReturn(false);

        $reflection = new \ReflectionClass($this->testEmailTab);
        $method = $reflection->getMethod('validateRecipient');
        $method->setAccessible(true);

        $result = $method->invoke($this->testEmailTab, 'invalid-email');

        $this->assertFalse($result);
    }

    public function testValidateRecipientReturnsFalseForEmptyEmail(): void
    {
        $reflection = new \ReflectionClass($this->testEmailTab);
        $method = $reflection->getMethod('validateRecipient');
        $method->setAccessible(true);

        $result = $method->invoke($this->testEmailTab, '');

        $this->assertFalse($result);
    }

    public function testValidateSmtpConfigurationReturnsTrueWhenConfigured(): void
    {
        $dto = new SmtpSettingsDTO();
        $dto->smtpUsername = 'user@example.com';
        $dto->smtpPassword = 'encrypted_password';
        $dto->fromEmail = 'from@example.com';

        $this->config
            ->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($dto);

        $reflection = new \ReflectionClass($this->testEmailTab);
        $method = $reflection->getMethod('validateSmtpConfiguration');
        $method->setAccessible(true);

        $result = $method->invoke($this->testEmailTab);

        $this->assertTrue($result);
    }

    public function testValidateSmtpConfigurationReturnsFalseWhenNotConfigured(): void
    {
        $dto = new SmtpSettingsDTO();
        $dto->smtpUsername = '';
        $dto->smtpPassword = '';
        $dto->fromEmail = '';

        $this->config
            ->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($dto);

        $reflection = new \ReflectionClass($this->testEmailTab);
        $method = $reflection->getMethod('validateSmtpConfiguration');
        $method->setAccessible(true);

        $result = $method->invoke($this->testEmailTab);

        $this->assertFalse($result);
    }
}