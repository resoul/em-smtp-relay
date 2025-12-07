<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Core;

use Emercury\Smtp\Core\Mailer;
use Emercury\Smtp\Config\Config;
use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;
use Emercury\Smtp\Config\DTO\AdvancedSettingsDTO;
use Emercury\Smtp\Contracts\EncryptionInterface;
use Emercury\Smtp\Contracts\ConfigInterface;
use Emercury\Smtp\Contracts\LoggerInterface;
use Emercury\Smtp\Contracts\EmailLoggerInterface;
use Emercury\Smtp\Events\EventManager;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Mockery;
use PHPMailer\PHPMailer\PHPMailer;

class MailerTest extends TestCase
{
    private Mailer $mailer;
    private $encryption;
    private $config;
    private $emailLogger;
    private $logger;
    private $eventManager;
    private $phpMailer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encryption = Mockery::mock(EncryptionInterface::class);
        $this->config = Mockery::mock(ConfigInterface::class);
        $this->emailLogger = Mockery::mock(EmailLoggerInterface::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->eventManager = Mockery::mock(EventManager::class);
        $this->phpMailer = Mockery::mock(PHPMailer::class);

        $this->mailer = new Mailer(
            $this->encryption,
            $this->config,
            $this->emailLogger,
            $this->logger,
            $this->eventManager
        );

        Functions\when('error_log')->justReturn(null);
        Functions\when('is_email')->returnArg();
        Functions\when('apply_filters')->returnArg();
        Functions\when('get_bloginfo')->justReturn('UTF-8');
        Functions\when('sanitize_email')->returnArg();
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('sanitize_file_name')->returnArg();

        if (!defined('ABSPATH')) {
            define('ABSPATH', '/var/www/html/');
        }

        if (!defined('WPINC')) {
            define('WPINC', 'wp-includes');
        }
    }

    // sendMail() - validation tests
    public function testSendMailReturnsFalseWhenSettingsNotConfigured(): void
    {
        $settings = new SmtpSettingsDTO('', '', 'tls', '', '');

        $this->config
            ->shouldReceive('getGeneralSettings')
            ->once()
            ->andReturn($settings);

        $this->config
            ->shouldReceive('getAdvancedSettings')
            ->once()
            ->andReturn(new AdvancedSettingsDTO());

        $this->logger
            ->shouldReceive('error')
            ->once()
            ->with('SMTP settings not configured');

        Actions\expectDone('wp_mail_failed')
            ->once();

        global $phpmailer;
        $phpmailer = $this->phpMailer;

        $this->setupPhpMailerMethods();

        $result = $this->mailer->sendMail(
            true,
            ['to' => 'test@example.com', 'subject' => 'Test', 'message' => 'Test message']
        );

        $this->assertFalse($result);
    }

    public function testSendMailReturnsFalseWhenUsernameIsMissing(): void
    {
        $settings = new SmtpSettingsDTO('', 'password', 'tls', 'from@example.com', 'From Name');

        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);
        $this->config->shouldReceive('getAdvancedSettings')->andReturn(new AdvancedSettingsDTO());
        $this->logger->shouldReceive('error')->once();

        Actions\expectDone('wp_mail_failed')->once();

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();

        $result = $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test'
        ]);

        $this->assertFalse($result);
    }

    public function testSendMailReturnsFalseWhenPasswordIsMissing(): void
    {
        $settings = new SmtpSettingsDTO('user@example.com', '', 'tls', 'from@example.com', 'From Name');

        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);
        $this->config->shouldReceive('getAdvancedSettings')->andReturn(new AdvancedSettingsDTO());
        $this->logger->shouldReceive('error')->once();

        Actions\expectDone('wp_mail_failed')->once();

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();

        $result = $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test'
        ]);

        $this->assertFalse($result);
    }

    public function testSendMailReturnsFalseWhenFromEmailIsMissing(): void
    {
        $settings = new SmtpSettingsDTO('user@example.com', 'encrypted', 'tls', '', 'From Name');

        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);
        $this->config->shouldReceive('getAdvancedSettings')->andReturn(new AdvancedSettingsDTO());
        $this->logger->shouldReceive('error')->once();

        Actions\expectDone('wp_mail_failed')->once();

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();

        $result = $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test'
        ]);

        $this->assertFalse($result);
    }

    // sendMail() - PHPMailer configuration tests
    public function testSendMailConfiguresSmtpSettings(): void
    {
        $settings = new SmtpSettingsDTO(
            'user@example.com',
            'encrypted_password',
            'tls',
            'from@example.com',
            'From Name'
        );

        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);
        $this->config->shouldReceive('getAdvancedSettings')->andReturn(new AdvancedSettingsDTO());
        $this->config->shouldReceive('getSmtpPort')->with('tls')->andReturn(587);

        $this->encryption
            ->shouldReceive('decrypt')
            ->once()
            ->with('encrypted_password')
            ->andReturn('decrypted_password');

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer->SMTPAuth = false;
        $this->phpMailer->Host = '';
        $this->phpMailer->Username = '';
        $this->phpMailer->Password = '';
        $this->phpMailer->SMTPSecure = '';
        $this->phpMailer->Port = 0;

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test message'
        ]);

        $this->assertTrue($this->phpMailer->SMTPAuth);
        $this->assertEquals(Config::SMTP_HOST, $this->phpMailer->Host);
        $this->assertEquals('user@example.com', $this->phpMailer->Username);
        $this->assertEquals('decrypted_password', $this->phpMailer->Password);
        $this->assertEquals('tls', $this->phpMailer->SMTPSecure);
        $this->assertEquals(587, $this->phpMailer->Port);
    }

    public function testSendMailConfiguresSslPort(): void
    {
        $settings = new SmtpSettingsDTO(
            'user@example.com',
            'encrypted',
            'ssl',
            'from@example.com',
            'From Name'
        );

        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);
        $this->config->shouldReceive('getAdvancedSettings')->andReturn(new AdvancedSettingsDTO());
        $this->config->shouldReceive('getSmtpPort')->with('ssl')->andReturn(465);

        $this->encryption->shouldReceive('decrypt')->andReturn('password');

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer->Port = 0;

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test'
        ]);

        $this->assertEquals(465, $this->phpMailer->Port);
    }

    public function testSendMailThrowsExceptionWhenDecryptionFails(): void
    {
        $settings = new SmtpSettingsDTO(
            'user@example.com',
            'encrypted',
            'tls',
            'from@example.com',
            'From Name'
        );

        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);
        $this->config->shouldReceive('getAdvancedSettings')->andReturn(new AdvancedSettingsDTO());
        $this->config->shouldReceive('getSmtpPort')->andReturn(587);

        $this->encryption
            ->shouldReceive('decrypt')
            ->once()
            ->andReturn('');

        $this->logger->shouldReceive('error')->once();
        Actions\expectDone('wp_mail_failed')->once();

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();

        $result = $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test'
        ]);

        $this->assertFalse($result);
    }

    // sendMail() - email preparation tests
    public function testSendMailSetsSubjectAndBody(): void
    {
        $this->setupValidSettings();

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer->Subject = '';
        $this->phpMailer->Body = '';

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test Subject',
            'message' => 'Test Message Body'
        ]);

        $this->assertEquals('Test Subject', $this->phpMailer->Subject);
        $this->assertEquals('Test Message Body', $this->phpMailer->Body);
    }

    public function testSendMailSetsCharset(): void
    {
        $this->setupValidSettings();

        Functions\expect('get_bloginfo')
            ->once()
            ->with('charset')
            ->andReturn('UTF-8');

        Functions\expect('apply_filters')
            ->once()
            ->with('wp_mail_charset', 'UTF-8')
            ->andReturn('UTF-8');

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer->CharSet = '';

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test'
        ]);

        $this->assertEquals('UTF-8', $this->phpMailer->CharSet);
    }

    public function testSendMailSetsHtmlContentType(): void
    {
        $this->setupValidSettings();

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer
            ->shouldReceive('isHTML')
            ->once()
            ->with(true);

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test',
            'headers' => ['Content-Type: text/html']
        ]);
    }

    public function testSendMailSetsPlainTextByDefault(): void
    {
        $this->setupValidSettings();

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer
            ->shouldReceive('isHTML')
            ->never();

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test'
        ]);
    }

    // sendMail() - from address tests
    public function testSendMailSetsFromAddressFromSettings(): void
    {
        $this->setupValidSettings();

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer
            ->shouldReceive('setFrom')
            ->once()
            ->with('from@example.com', 'From Name', false);

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test'
        ]);
    }

    public function testSendMailUsesHeaderFromWhenForceFromIsDisabled(): void
    {
        $settings = new SmtpSettingsDTO(
            'user@example.com',
            'encrypted',
            'tls',
            'from@example.com',
            'From Name',
            0  // forceFromAddress = false
        );

        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);
        $this->config->shouldReceive('getAdvancedSettings')->andReturn(new AdvancedSettingsDTO());
        $this->config->shouldReceive('getSmtpPort')->andReturn(587);
        $this->encryption->shouldReceive('decrypt')->andReturn('password');

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer
            ->shouldReceive('setFrom')
            ->once()
            ->with('custom@example.com', 'Custom Name', false);

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test',
            'headers' => ['From: Custom Name <custom@example.com>']
        ]);
    }

    public function testSendMailIgnoresHeaderFromWhenForceFromIsEnabled(): void
    {
        $settings = new SmtpSettingsDTO(
            'user@example.com',
            'encrypted',
            'tls',
            'from@example.com',
            'From Name',
            1  // forceFromAddress = true
        );

        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);
        $this->config->shouldReceive('getAdvancedSettings')->andReturn(new AdvancedSettingsDTO());
        $this->config->shouldReceive('getSmtpPort')->andReturn(587);
        $this->encryption->shouldReceive('decrypt')->andReturn('password');

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer
            ->shouldReceive('setFrom')
            ->once()
            ->with('from@example.com', 'From Name', false);

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test',
            'headers' => ['From: Custom Name <custom@example.com>']
        ]);
    }

    // sendMail() - recipients tests
    public function testSendMailAddsRecipient(): void
    {
        $this->setupValidSettings();

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer
            ->shouldReceive('addAddress')
            ->once()
            ->with('test@example.com', '');

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test'
        ]);
    }

    public function testSendMailAddsMultipleRecipients(): void
    {
        $this->setupValidSettings();

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer
            ->shouldReceive('addAddress')
            ->once()
            ->with('test1@example.com', '');

        $this->phpMailer
            ->shouldReceive('addAddress')
            ->once()
            ->with('test2@example.com', '');

        $this->mailer->sendMail(true, [
            'to' => ['test1@example.com', 'test2@example.com'],
            'subject' => 'Test',
            'message' => 'Test'
        ]);
    }

    public function testSendMailParsesRecipientWithName(): void
    {
        $this->setupValidSettings();

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer
            ->shouldReceive('addAddress')
            ->once()
            ->with('test@example.com', 'Test User');

        $this->mailer->sendMail(true, [
            'to' => 'Test User <test@example.com>',
            'subject' => 'Test',
            'message' => 'Test'
        ]);
    }

    // sendMail() - attachments tests
    public function testSendMailAddsAttachment(): void
    {
        $this->setupValidSettings();

        Functions\expect('file_exists')
            ->once()
            ->with('/path/to/file.pdf')
            ->andReturn(true);

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer
            ->shouldReceive('addAttachment')
            ->once()
            ->with('/path/to/file.pdf', '');

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test',
            'attachments' => ['/path/to/file.pdf']
        ]);
    }

    public function testSendMailAddsMultipleAttachments(): void
    {
        $this->setupValidSettings();

        Functions\expect('file_exists')
            ->times(2)
            ->andReturn(true);

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer
            ->shouldReceive('addAttachment')
            ->twice();

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test',
            'attachments' => ['/path/to/file1.pdf', '/path/to/file2.jpg']
        ]);
    }

    public function testSendMailSkipsNonExistentAttachments(): void
    {
        $this->setupValidSettings();

        Functions\expect('file_exists')
            ->once()
            ->with('/nonexistent/file.pdf')
            ->andReturn(false);

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer
            ->shouldReceive('addAttachment')
            ->never();

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test',
            'attachments' => ['/nonexistent/file.pdf']
        ]);
    }

    public function testSendMailAddsAttachmentWithCustomFilename(): void
    {
        $this->setupValidSettings();

        Functions\expect('file_exists')
            ->once()
            ->andReturn(true);

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer
            ->shouldReceive('addAttachment')
            ->once()
            ->with('/path/to/file.pdf', 'custom-name.pdf');

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test',
            'attachments' => ['custom-name.pdf' => '/path/to/file.pdf']
        ]);
    }

    // sendMail() - reply-to tests
    public function testSendMailAddsReplyToFromAdvancedSettings(): void
    {
        $settings = new SmtpSettingsDTO('user@example.com', 'encrypted', 'tls', 'from@example.com', 'From Name');
        $advancedSettings = new AdvancedSettingsDTO('reply@example.com', 'Reply Name');

        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);
        $this->config->shouldReceive('getAdvancedSettings')->andReturn($advancedSettings);
        $this->config->shouldReceive('getSmtpPort')->andReturn(587);
        $this->encryption->shouldReceive('decrypt')->andReturn('password');

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer
            ->shouldReceive('addReplyTo')
            ->once()
            ->with('reply@example.com', 'Reply Name');

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test'
        ]);
    }

    public function testSendMailUsesHeaderReplyToWhenForceReplyToDisabled(): void
    {
        $settings = new SmtpSettingsDTO('user@example.com', 'encrypted', 'tls', 'from@example.com', 'From Name');
        $advancedSettings = new AdvancedSettingsDTO('reply@example.com', 'Reply Name', 0);

        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);
        $this->config->shouldReceive('getAdvancedSettings')->andReturn($advancedSettings);
        $this->config->shouldReceive('getSmtpPort')->andReturn(587);
        $this->encryption->shouldReceive('decrypt')->andReturn('password');

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer
            ->shouldReceive('addReplyTo')
            ->once()
            ->with('custom-reply@example.com', 'Custom Reply');

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test',
            'headers' => ['Reply-To: Custom Reply <custom-reply@example.com>']
        ]);
    }

    // sendMail() - CC/BCC tests
    public function testSendMailAddsCcFromAdvancedSettings(): void
    {
        $settings = new SmtpSettingsDTO('user@example.com', 'encrypted', 'tls', 'from@example.com', 'From Name');
        $advancedSettings = new AdvancedSettingsDTO('', '', 0, 'cc@example.com', 'CC Name', 1);

        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);
        $this->config->shouldReceive('getAdvancedSettings')->andReturn($advancedSettings);
        $this->config->shouldReceive('getSmtpPort')->andReturn(587);
        $this->encryption->shouldReceive('decrypt')->andReturn('password');

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer
            ->shouldReceive('addCc')
            ->once()
            ->with('cc@example.com', 'CC Name');

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test'
        ]);
    }

    public function testSendMailAddsBccFromAdvancedSettings(): void
    {
        $settings = new SmtpSettingsDTO('user@example.com', 'encrypted', 'tls', 'from@example.com', 'From Name');
        $advancedSettings = new AdvancedSettingsDTO('', '', 0, '', '', 0, 'bcc@example.com', 'BCC Name', 1);

        $this->config->shouldReceive('getGeneralSettings')->andReturn($settings);
        $this->config->shouldReceive('getAdvancedSettings')->andReturn($advancedSettings);
        $this->config->shouldReceive('getSmtpPort')->andReturn(587);
        $this->encryption->shouldReceive('decrypt')->andReturn('password');

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer
            ->shouldReceive('addBcc')
            ->once()
            ->with('bcc@example.com', 'BCC Name');

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test'
        ]);
    }

    // sendMail() - debug mode tests
    public function testSendMailEnablesDebugModeWhenHeaderPresent(): void
    {
        $this->setupValidSettings();

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer->SMTPDebug = 0;

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test',
            'headers' => ['EM-SMTP-Debug: True']
        ]);

        $this->assertEquals(2, $this->phpMailer->SMTPDebug);
    }

    public function testSendMailDoesNotEnableDebugModeByDefault(): void
    {
        $this->setupValidSettings();

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();
        $this->setupSuccessfulSend();

        $this->phpMailer->SMTPDebug = 0;

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test'
        ]);

        $this->assertEquals(0, $this->phpMailer->SMTPDebug);
    }

    // sendMail() - logging tests
    public function testSendMailLogsSuccessfulSend(): void
    {
        $this->setupValidSettings();

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();

        $this->phpMailer
            ->shouldReceive('send')
            ->once()
            ->andReturn(true);

        $this->emailLogger
            ->shouldReceive('logSent')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['to'] === 'test@example.com'
                    && $data['subject'] === 'Test Subject';
            }));

        Actions\expectDone('wp_mail_succeeded')->once();

        $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test Subject',
            'message' => 'Test'
        ]);
    }

    public function testSendMailLogsFailedSend(): void
    {
        $this->setupValidSettings();

        global $phpmailer;
        $phpmailer = $this->phpMailer;
        $this->setupPhpMailerMethods();

        $exception = new \PHPMailer\PHPMailer\Exception('SMTP Error');

        $this->phpMailer
            ->shouldReceive('send')
            ->once()
            ->andThrow($exception);

        $this->emailLogger
            ->shouldReceive('logFailed')
            ->once()
            ->with(
                Mockery::on(function($data) {
                    return isset($data['to']) && isset($data['subject']);
                }),
                'SMTP Error'
            );

        Actions\expectDone('wp_mail_failed')->once();

        $result = $this->mailer->sendMail(true, [
            'to' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test'
        ]);

        $this->assertFalse($result);
    }

    // Helper methods
    private function setupValidSettings(): void
    {
        $settings = new SmtpSettingsDTO(
            'user@example.com',
            'encrypted_password',
            'tls',
            'from@example.com',
            'From Name'
        );

        $this->config
            ->shouldReceive('getGeneralSettings')
            ->andReturn($settings);

        $this->config
            ->shouldReceive('getAdvancedSettings')
            ->andReturn(new AdvancedSettingsDTO());

        $this->config
            ->shouldReceive('getSmtpPort')
            ->andReturn(587);

        $this->encryption
            ->shouldReceive('decrypt')
            ->andReturn('decrypted_password');
    }

    private function setupPhpMailerMethods(): void
    {
        $this->phpMailer
            ->shouldReceive('isSMTP')
            ->andReturn(null);

        $this->phpMailer
            ->shouldReceive('setFrom')
            ->andReturn(null);

        $this->phpMailer
            ->shouldReceive('addAddress')
            ->andReturn(null);

        $this->phpMailer
            ->shouldReceive('addReplyTo')
            ->andReturn(null);

        $this->phpMailer
            ->shouldReceive('addCc')
            ->andReturn(null);

        $this->phpMailer
            ->shouldReceive('addBcc')
            ->andReturn(null);

        $this->phpMailer
            ->shouldReceive('addAttachment')
            ->andReturn(null);

        $this->phpMailer
            ->shouldReceive('clearAllRecipients')
            ->andReturn(null);

        $this->phpMailer
            ->shouldReceive('clearAttachments')
            ->andReturn(null);

        $this->phpMailer
            ->shouldReceive('clearCustomHeaders')
            ->andReturn(null);

        $this->phpMailer
            ->shouldReceive('clearReplyTos')
            ->andReturn(null);

        Actions\expectDone('phpmailer_init');
    }

    private function setupSuccessfulSend(): void
    {
        $this->phpMailer
            ->shouldReceive('send')
            ->andReturn(true);

        $this->emailLogger
            ->shouldReceive('logSent')
            ->andReturn(null);

        Actions\expectDone('wp_mail_succeeded');
    }

    protected function tearDown(): void
    {
        global $phpmailer;
        $phpmailer = null;

        parent::tearDown();
    }
}