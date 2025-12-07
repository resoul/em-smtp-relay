<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Config\DTO;

use Emercury\Smtp\Config\Config;
use Emercury\Smtp\Config\DTO\SmtpSettingsDTO;
use Emercury\Smtp\Config\SettingKeys;
use Emercury\Smtp\Core\RequestHandler;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;

class SmtpSettingsDTOTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('sanitize_email')->returnArg();
        Functions\when('wp_kses_post')->returnArg();
        Functions\when('esc_url_raw')->returnArg();
    }

    public function testConstructorWithDefaultValues(): void
    {
        $dto = new SmtpSettingsDTO();

        $this->assertSame('', $dto->smtpUsername);
        $this->assertSame('', $dto->smtpPassword);
        $this->assertSame('tls', $dto->smtpEncryption);
        $this->assertSame('', $dto->fromEmail);
        $this->assertSame('', $dto->fromName);
        $this->assertSame(0, $dto->forceFromAddress);
        $this->assertSame(587, $dto->smtpPort);
    }

    public function testConstructorWithAllParameters(): void
    {
        $dto = new SmtpSettingsDTO(
            'user@example.com',
            'password123',
            'ssl',
            'from@example.com',
            'From Name',
            1,
            465
        );

        $this->assertSame('user@example.com', $dto->smtpUsername);
        $this->assertSame('password123', $dto->smtpPassword);
        $this->assertSame('ssl', $dto->smtpEncryption);
        $this->assertSame('from@example.com', $dto->fromEmail);
        $this->assertSame('From Name', $dto->fromName);
        $this->assertSame(1, $dto->forceFromAddress);
        $this->assertSame(465, $dto->smtpPort);
    }

    public function testConstructorWithTlsEncryption(): void
    {
        $dto = new SmtpSettingsDTO(
            'user@example.com',
            'password',
            'tls',
            'from@example.com',
            'From Name'
        );

        $this->assertSame('tls', $dto->smtpEncryption);
        $this->assertSame(587, $dto->smtpPort);
    }

    public function testConstructorWithSslEncryption(): void
    {
        $dto = new SmtpSettingsDTO(
            'user@example.com',
            'password',
            'ssl',
            'from@example.com',
            'From Name'
        );

        $this->assertSame('ssl', $dto->smtpEncryption);
        $this->assertSame(465, $dto->smtpPort);
    }

    public function testConstructorAutoSetsPortForTls(): void
    {
        $dto = new SmtpSettingsDTO(
            'user@example.com',
            'password',
            'tls',
            'from@example.com',
            'From Name',
            0,
            0
        );

        $this->assertSame(587, $dto->smtpPort);
    }

    public function testConstructorAutoSetsPortForSsl(): void
    {
        $dto = new SmtpSettingsDTO(
            'user@example.com',
            'password',
            'ssl',
            'from@example.com',
            'From Name',
            0,
            0
        );

        $this->assertSame(465, $dto->smtpPort);
    }

    public function testConstructorWithCustomPort(): void
    {
        $dto = new SmtpSettingsDTO(
            'user@example.com',
            'password',
            'tls',
            'from@example.com',
            'From Name',
            0,
            2525
        );

        $this->assertSame(2525, $dto->smtpPort);
    }

    public function testFromArrayWithCompleteData(): void
    {
        $data = [
            SettingKeys::USERNAME => 'user@example.com',
            SettingKeys::PASSWORD => 'encrypted_password',
            SettingKeys::ENCRYPTION => 'tls',
            SettingKeys::FROM_EMAIL => 'from@example.com',
            SettingKeys::FROM_NAME => 'From Name',
            SettingKeys::FORCE_FROM_ADDRESS => 1,
            SettingKeys::PORT => 587,
        ];

        $dto = SmtpSettingsDTO::fromArray($data);

        $this->assertSame('user@example.com', $dto->smtpUsername);
        $this->assertSame('encrypted_password', $dto->smtpPassword);
        $this->assertSame('tls', $dto->smtpEncryption);
        $this->assertSame('from@example.com', $dto->fromEmail);
        $this->assertSame('From Name', $dto->fromName);
        $this->assertSame(1, $dto->forceFromAddress);
        $this->assertSame(587, $dto->smtpPort);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $dto = SmtpSettingsDTO::fromArray([]);

        $this->assertSame('', $dto->smtpUsername);
        $this->assertSame('', $dto->smtpPassword);
        $this->assertSame('tls', $dto->smtpEncryption);
        $this->assertSame('', $dto->fromEmail);
        $this->assertSame('', $dto->fromName);
        $this->assertSame(0, $dto->forceFromAddress);
        $this->assertSame(587, $dto->smtpPort);
    }

    public function testFromArrayWithPartialData(): void
    {
        $data = [
            SettingKeys::USERNAME => 'user@example.com',
            SettingKeys::FROM_EMAIL => 'from@example.com',
        ];

        $dto = SmtpSettingsDTO::fromArray($data);

        $this->assertSame('user@example.com', $dto->smtpUsername);
        $this->assertSame('', $dto->smtpPassword);
        $this->assertSame('tls', $dto->smtpEncryption);
        $this->assertSame('from@example.com', $dto->fromEmail);
        $this->assertSame('', $dto->fromName);
        $this->assertSame(0, $dto->forceFromAddress);
    }

    public function testFromArrayWithZeroPort(): void
    {
        $data = [
            SettingKeys::ENCRYPTION => 'ssl',
            SettingKeys::PORT => 0,
        ];

        $dto = SmtpSettingsDTO::fromArray($data);

        $this->assertSame(465, $dto->smtpPort);
    }

    public function testFromArrayWithIntegerStrings(): void
    {
        $data = [
            SettingKeys::FORCE_FROM_ADDRESS => 1,
            SettingKeys::PORT => '2525',
        ];

        $dto = SmtpSettingsDTO::fromArray($data);

        $this->assertSame(1, $dto->forceFromAddress);
        $this->assertSame(2525, $dto->smtpPort);
    }

    public function testFromRequestWithCompleteData(): void
    {
        $requestData = [
            SettingKeys::USERNAME => 'user@example.com',
            SettingKeys::PASSWORD => 'password123',
            SettingKeys::ENCRYPTION => 'tls',
            SettingKeys::FROM_EMAIL => 'from@example.com',
            SettingKeys::FROM_NAME => 'From Name',
            SettingKeys::FORCE_FROM_ADDRESS => '1',
            SettingKeys::PORT => '587',
        ];

        $request = new RequestHandler($requestData);
        $dto = SmtpSettingsDTO::fromRequest($request);

        $this->assertSame('user@example.com', $dto->smtpUsername);
        $this->assertSame('password123', $dto->smtpPassword);
        $this->assertSame('tls', $dto->smtpEncryption);
        $this->assertSame('from@example.com', $dto->fromEmail);
        $this->assertSame('From Name', $dto->fromName);
        $this->assertSame(1, $dto->forceFromAddress);
        $this->assertSame(587, $dto->smtpPort);
    }

    public function testFromRequestWithEmptyData(): void
    {
        $request = new RequestHandler([]);
        $dto = SmtpSettingsDTO::fromRequest($request);

        $this->assertSame('', $dto->smtpUsername);
        $this->assertSame('', $dto->smtpPassword);
        $this->assertSame('tls', $dto->smtpEncryption);
        $this->assertSame('', $dto->fromEmail);
        $this->assertSame('', $dto->fromName);
        $this->assertSame(0, $dto->forceFromAddress);
        $this->assertSame(587, $dto->smtpPort);
    }

    public function testFromRequestWithTlsEncryption(): void
    {
        $requestData = [
            SettingKeys::ENCRYPTION => 'tls',
        ];

        $request = new RequestHandler($requestData);
        $dto = SmtpSettingsDTO::fromRequest($request);

        $this->assertSame('tls', $dto->smtpEncryption);
        $this->assertSame(587, $dto->smtpPort);
    }

    public function testFromRequestWithSslEncryption(): void
    {
        $requestData = [
            SettingKeys::ENCRYPTION => 'ssl',
        ];

        $request = new RequestHandler($requestData);
        $dto = SmtpSettingsDTO::fromRequest($request);

        $this->assertSame('ssl', $dto->smtpEncryption);
        $this->assertSame(465, $dto->smtpPort);
    }

    public function testFromRequestWithCustomPort(): void
    {
        $requestData = [
            SettingKeys::ENCRYPTION => 'tls',
            SettingKeys::PORT => '2525',
        ];

        $request = new RequestHandler($requestData);
        $dto = SmtpSettingsDTO::fromRequest($request);

        $this->assertSame(2525, $dto->smtpPort);
    }

    public function testToArrayReturnsCompleteArray(): void
    {
        $dto = new SmtpSettingsDTO(
            'user@example.com',
            'encrypted_password',
            'tls',
            'from@example.com',
            'From Name',
            1,
            587
        );

        $array = $dto->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey(SettingKeys::USERNAME, $array);
        $this->assertArrayHasKey(SettingKeys::PASSWORD, $array);
        $this->assertArrayHasKey(SettingKeys::ENCRYPTION, $array);
        $this->assertArrayHasKey(SettingKeys::FROM_EMAIL, $array);
        $this->assertArrayHasKey(SettingKeys::FROM_NAME, $array);
        $this->assertArrayHasKey(SettingKeys::FORCE_FROM_ADDRESS, $array);
        $this->assertArrayHasKey(SettingKeys::HOST, $array);
        $this->assertArrayHasKey(SettingKeys::AUTH, $array);
        $this->assertArrayHasKey(SettingKeys::PORT, $array);

        $this->assertSame('user@example.com', $array[SettingKeys::USERNAME]);
        $this->assertSame('encrypted_password', $array[SettingKeys::PASSWORD]);
        $this->assertSame('tls', $array[SettingKeys::ENCRYPTION]);
        $this->assertSame('from@example.com', $array[SettingKeys::FROM_EMAIL]);
        $this->assertSame('From Name', $array[SettingKeys::FROM_NAME]);
        $this->assertSame(1, $array[SettingKeys::FORCE_FROM_ADDRESS]);
        $this->assertSame(Config::SMTP_HOST, $array[SettingKeys::HOST]);
        $this->assertSame('true', $array[SettingKeys::AUTH]);
        $this->assertSame(587, $array[SettingKeys::PORT]);
    }

    public function testToArrayWithDefaultValues(): void
    {
        $dto = new SmtpSettingsDTO();
        $array = $dto->toArray();

        $this->assertSame('', $array[SettingKeys::USERNAME]);
        $this->assertSame('', $array[SettingKeys::PASSWORD]);
        $this->assertSame('tls', $array[SettingKeys::ENCRYPTION]);
        $this->assertSame('', $array[SettingKeys::FROM_EMAIL]);
        $this->assertSame('', $array[SettingKeys::FROM_NAME]);
        $this->assertSame(0, $array[SettingKeys::FORCE_FROM_ADDRESS]);
        $this->assertSame(Config::SMTP_HOST, $array[SettingKeys::HOST]);
        $this->assertSame('true', $array[SettingKeys::AUTH]);
        $this->assertSame(587, $array[SettingKeys::PORT]);
    }

    public function testToArrayAlwaysIncludesHostAndAuth(): void
    {
        $dto = new SmtpSettingsDTO();
        $array = $dto->toArray();

        $this->assertSame(Config::SMTP_HOST, $array[SettingKeys::HOST]);
        $this->assertSame('true', $array[SettingKeys::AUTH]);
    }

    public function testRoundTripConversionFromArrayToArray(): void
    {
        $originalData = [
            SettingKeys::USERNAME => 'user@example.com',
            SettingKeys::PASSWORD => 'password123',
            SettingKeys::ENCRYPTION => 'ssl',
            SettingKeys::FROM_EMAIL => 'from@example.com',
            SettingKeys::FROM_NAME => 'From Name',
            SettingKeys::FORCE_FROM_ADDRESS => 1,
            SettingKeys::PORT => 465,
        ];

        $dto = SmtpSettingsDTO::fromArray($originalData);
        $resultData = $dto->toArray();

        $this->assertSame($originalData[SettingKeys::USERNAME], $resultData[SettingKeys::USERNAME]);
        $this->assertSame($originalData[SettingKeys::PASSWORD], $resultData[SettingKeys::PASSWORD]);
        $this->assertSame($originalData[SettingKeys::ENCRYPTION], $resultData[SettingKeys::ENCRYPTION]);
        $this->assertSame($originalData[SettingKeys::FROM_EMAIL], $resultData[SettingKeys::FROM_EMAIL]);
        $this->assertSame($originalData[SettingKeys::FROM_NAME], $resultData[SettingKeys::FROM_NAME]);
        $this->assertSame($originalData[SettingKeys::FORCE_FROM_ADDRESS], $resultData[SettingKeys::FORCE_FROM_ADDRESS]);
        $this->assertSame($originalData[SettingKeys::PORT], $resultData[SettingKeys::PORT]);
    }

    public function testRoundTripConversionFromRequestToArray(): void
    {
        $requestData = [
            SettingKeys::USERNAME => 'user@example.com',
            SettingKeys::PASSWORD => 'password123',
            SettingKeys::ENCRYPTION => 'tls',
            SettingKeys::FROM_EMAIL => 'from@example.com',
            SettingKeys::FROM_NAME => 'From Name',
            SettingKeys::FORCE_FROM_ADDRESS => '1',
            SettingKeys::PORT => '587',
        ];

        $request = new RequestHandler($requestData);
        $dto = SmtpSettingsDTO::fromRequest($request);
        $resultArray = $dto->toArray();

        $this->assertSame('user@example.com', $resultArray[SettingKeys::USERNAME]);
        $this->assertSame('password123', $resultArray[SettingKeys::PASSWORD]);
        $this->assertSame('tls', $resultArray[SettingKeys::ENCRYPTION]);
        $this->assertSame('from@example.com', $resultArray[SettingKeys::FROM_EMAIL]);
        $this->assertSame('From Name', $resultArray[SettingKeys::FROM_NAME]);
        $this->assertSame(1, $resultArray[SettingKeys::FORCE_FROM_ADDRESS]);
        $this->assertSame(587, $resultArray[SettingKeys::PORT]);
    }

    public function testFromArrayHandlesNullValues(): void
    {
        $data = [
            SettingKeys::USERNAME => null,
            SettingKeys::PASSWORD => null,
            SettingKeys::FROM_EMAIL => null,
        ];

        $dto = SmtpSettingsDTO::fromArray($data);

        $this->assertSame('', $dto->smtpUsername);
        $this->assertSame('', $dto->smtpPassword);
        $this->assertSame('', $dto->fromEmail);
    }

    public function testModifyPropertiesAfterCreation(): void
    {
        $dto = new SmtpSettingsDTO();

        $dto->smtpUsername = 'new-user@example.com';
        $dto->smtpPassword = 'new-password';
        $dto->smtpEncryption = 'ssl';
        $dto->fromEmail = 'new-from@example.com';
        $dto->fromName = 'New From Name';
        $dto->forceFromAddress = 1;
        $dto->smtpPort = 465;

        $this->assertSame('new-user@example.com', $dto->smtpUsername);
        $this->assertSame('new-password', $dto->smtpPassword);
        $this->assertSame('ssl', $dto->smtpEncryption);
        $this->assertSame('new-from@example.com', $dto->fromEmail);
        $this->assertSame('New From Name', $dto->fromName);
        $this->assertSame(1, $dto->forceFromAddress);
        $this->assertSame(465, $dto->smtpPort);
    }

    public function testToArrayAfterModification(): void
    {
        $dto = new SmtpSettingsDTO();

        $dto->smtpUsername = 'modified@example.com';
        $dto->smtpPassword = 'modified-password';
        $dto->fromEmail = 'modified-from@example.com';
        $dto->forceFromAddress = 1;

        $array = $dto->toArray();

        $this->assertSame('modified@example.com', $array[SettingKeys::USERNAME]);
        $this->assertSame('modified-password', $array[SettingKeys::PASSWORD]);
        $this->assertSame('modified-from@example.com', $array[SettingKeys::FROM_EMAIL]);
        $this->assertSame(1, $array[SettingKeys::FORCE_FROM_ADDRESS]);
    }

    public function testPortAutoSelectionWithDifferentEncryptionTypes(): void
    {
        $tlsDto = new SmtpSettingsDTO('', '', 'tls');
        $this->assertSame(587, $tlsDto->smtpPort);

        $sslDto = new SmtpSettingsDTO('', '', 'ssl');
        $this->assertSame(465, $sslDto->smtpPort);
    }

    public function testPortOverridesAutoSelection(): void
    {
        $dto = new SmtpSettingsDTO('', '', 'tls', '', '', 0, 2525);
        $this->assertSame(2525, $dto->smtpPort);

        $dto2 = new SmtpSettingsDTO('', '', 'ssl', '', '', 0, 2525);
        $this->assertSame(2525, $dto2->smtpPort);
    }
}