<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Config;

use Emercury\Smtp\Config\Config;
use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;
use Emercury\Smtp\Config\DTO\AdvancedSettingsDTO;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;

class ConfigTest extends TestCase
{
    private Config $config;
    private array $options = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->options = [];

        Functions\when('get_option')->alias(function($key, $default = false) {
            return $this->options[$key] ?? $default;
        });

        Functions\when('update_option')->alias(function($key, $value) {
            $this->options[$key] = $value;
            return true;
        });

        $this->config = new Config();
    }

    public function testGetSmtpPortForTls(): void
    {
        $port = $this->config->getSmtpPort('tls');
        $this->assertEquals(587, $port);
    }

    public function testGetSmtpPortForSsl(): void
    {
        $port = $this->config->getSmtpPort('ssl');
        $this->assertEquals(465, $port);
    }

    public function testGetGeneralSettingsReturnsDefaultsWhenEmpty(): void
    {
        $settings = $this->config->getGeneralSettings();

        $this->assertInstanceOf(SmtpSettingsDTO::class, $settings);
        $this->assertEmpty($settings->smtpUsername);
    }

    public function testGetGeneralSettingsReturnsStoredData(): void
    {
        $this->options[Config::OPTION_GENERAL] = [
            'em_smtp_relay_username' => 'test@example.com',
            'em_smtp_relay_password' => 'encrypted-pass',
            'em_smtp_relay_encryption' => 'tls',
            'em_smtp_relay_from_email' => 'from@example.com',
            'em_smtp_relay_from_name' => 'Test Name',
            'em_smtp_relay_force_from_address' => 0,
            'em_smtp_relay_port' => 587
        ];

        $settings = $this->config->getGeneralSettings();

        $this->assertEquals('test@example.com', $settings->smtpUsername);
        $this->assertEquals('tls', $settings->smtpEncryption);
    }

    public function testSaveGeneralSettings(): void
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

        $result = $this->config->saveGeneralSettings($dto);

        $this->assertTrue($result);
        $this->assertArrayHasKey(Config::OPTION_GENERAL, $this->options);

        $dto->smtpPassword = '';
        $this->config->saveGeneralSettings($dto);

        $settings = $this->config->getGeneralSettings();

        $this->assertNotEquals('', $settings->smtpPassword);
    }

    public function testGetAdvancedSettingsReturnsDefaults(): void
    {
        $settings = $this->config->getAdvancedSettings();

        $this->assertInstanceOf(AdvancedSettingsDTO::class, $settings);
        $this->assertEmpty($settings->replyToEmail);
    }

    public function testSaveAdvancedSettings(): void
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

        $result = $this->config->saveAdvancedSettings($dto);

        $this->assertTrue($result);
        $this->assertArrayHasKey(Config::OPTION_ADVANCED, $this->options);

        $settings = $this->config->getAdvancedSettings();
        $this->assertEquals('BCC Name', $settings->bccName);
    }
}