<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Security;

use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;
use Emercury\Smtp\Config\DTO\AdvancedSettingsDTO;
use Emercury\Smtp\Security\Validator;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;

class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('__')->returnArg();

        $this->validator = new Validator();
    }

    public function testValidateEmailWithValidEmail(): void
    {
        $result = $this->validator->validateEmail('test@example.com');
        $this->assertTrue($result);
    }

    public function testValidateEmailWithInvalidEmail(): void
    {
        $result = $this->validator->validateEmail('invalid-email');
        $this->assertFalse($result);
    }

    public function testValidateSmtpSettingsWithValidData(): void
    {
        $dto = new SmtpSettingsDTO(
            'user@example.com',
            'password',
            'tls',
            'from@example.com',
            'From Name',
            0,
            587
        );

        $errors = $this->validator->validateSmtpSettings($dto);
        $this->assertEmpty($errors);
    }

    public function testValidateSmtpSettingsWithEmptyUsername(): void
    {
        $dto = new SmtpSettingsDTO(
            '',
            'password',
            'tls',
            'from@example.com',
            'From Name'
        );

        $errors = $this->validator->validateSmtpSettings($dto);
        $this->assertNotEmpty($errors);
        $this->assertContains('SMTP Username is required', $errors);
    }

    public function testValidateSmtpSettingsWithInvalidFromEmail(): void
    {
        $dto = new SmtpSettingsDTO(
            'user@example.com',
            'password',
            'tls',
            'invalid-email',
            'From Name'
        );

        $errors = $this->validator->validateSmtpSettings($dto);
        $this->assertNotEmpty($errors);
        $this->assertContains('Invalid From Email Address', $errors);
    }

    public function testValidateSmtpSettingsWithInvalidEncryption(): void
    {
        $dto = new SmtpSettingsDTO(
            'user@example.com',
            'password',
            'invalid',
            'from@example.com',
            'From Name'
        );

        $errors = $this->validator->validateSmtpSettings($dto);
        $this->assertNotEmpty($errors);
        $this->assertContains('Invalid encryption type', $errors);
    }

    public function testValidateAdvancedSettingsWithValidData(): void
    {
        $dto = new AdvancedSettingsDTO(
            'reply@example.com',
            'Reply Name',
            0,
            'cc@example.com',
            'CC Name',
            0,
            'bcc@example.com',
            'BCC Name',
            0
        );

        $errors = $this->validator->validateAdvancedSettings($dto);
        $this->assertEmpty($errors);
    }

    public function testValidateAdvancedSettingsWithInvalidEmails(): void
    {
        $dto = new AdvancedSettingsDTO(
            'invalid-reply',
            'Reply Name',
            0,
            'invalid-cc',
            'CC Name',
            0,
            'invalid-bcc',
            'BCC Name',
            0
        );

        $errors = $this->validator->validateAdvancedSettings($dto);
        $this->assertCount(3, $errors);
    }
}