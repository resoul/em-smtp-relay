<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Config\DTO;

use Emercury\Smtp\Config\DTO\AdvancedSettingsDTO;
use Emercury\Smtp\Config\SettingKeys;
use Emercury\Smtp\Core\RequestHandler;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;

class AdvancedSettingsDTOTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('sanitize_email')->returnArg();
    }

    public function testConstructorWithDefaultValues(): void
    {
        $dto = new AdvancedSettingsDTO();

        $this->assertSame('', $dto->replyToEmail);
        $this->assertSame('', $dto->replyToName);
        $this->assertSame(0, $dto->forceReplyTo);
        $this->assertSame('', $dto->ccEmail);
        $this->assertSame('', $dto->ccName);
        $this->assertSame(0, $dto->forceCc);
        $this->assertSame('', $dto->bccEmail);
        $this->assertSame('', $dto->bccName);
        $this->assertSame(0, $dto->forceBcc);
    }

    public function testConstructorWithAllParameters(): void
    {
        $dto = new AdvancedSettingsDTO(
            'reply@example.com',
            'Reply Name',
            1,
            'cc@example.com',
            'CC Name',
            1,
            'bcc@example.com',
            'BCC Name',
            1
        );

        $this->assertSame('reply@example.com', $dto->replyToEmail);
        $this->assertSame('Reply Name', $dto->replyToName);
        $this->assertSame(1, $dto->forceReplyTo);
        $this->assertSame('cc@example.com', $dto->ccEmail);
        $this->assertSame('CC Name', $dto->ccName);
        $this->assertSame(1, $dto->forceCc);
        $this->assertSame('bcc@example.com', $dto->bccEmail);
        $this->assertSame('BCC Name', $dto->bccName);
        $this->assertSame(1, $dto->forceBcc);
    }

    public function testConstructorWithPartialParameters(): void
    {
        $dto = new AdvancedSettingsDTO(
            'reply@example.com',
            'Reply Name',
            1
        );

        $this->assertSame('reply@example.com', $dto->replyToEmail);
        $this->assertSame('Reply Name', $dto->replyToName);
        $this->assertSame(1, $dto->forceReplyTo);
        $this->assertSame('', $dto->ccEmail);
        $this->assertSame('', $dto->ccName);
        $this->assertSame(0, $dto->forceCc);
    }

    public function testFromArrayWithCompleteData(): void
    {
        $data = [
            SettingKeys::REPLY_TO_EMAIL => 'reply@example.com',
            SettingKeys::REPLY_TO_NAME => 'Reply Name',
            SettingKeys::FORCE_REPLY_TO => 1,
            SettingKeys::CC_EMAIL => 'cc@example.com',
            SettingKeys::CC_NAME => 'CC Name',
            SettingKeys::FORCE_CC => 1,
            SettingKeys::BCC_EMAIL => 'bcc@example.com',
            SettingKeys::BCC_NAME => 'BCC Name',
            SettingKeys::FORCE_BCC => 1,
        ];

        $dto = AdvancedSettingsDTO::fromArray($data);

        $this->assertSame('reply@example.com', $dto->replyToEmail);
        $this->assertSame('Reply Name', $dto->replyToName);
        $this->assertSame(1, $dto->forceReplyTo);
        $this->assertSame('cc@example.com', $dto->ccEmail);
        $this->assertSame('CC Name', $dto->ccName);
        $this->assertSame(1, $dto->forceCc);
        $this->assertSame('bcc@example.com', $dto->bccEmail);
        $this->assertSame('BCC Name', $dto->bccName);
        $this->assertSame(1, $dto->forceBcc);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $dto = AdvancedSettingsDTO::fromArray([]);

        $this->assertSame('', $dto->replyToEmail);
        $this->assertSame('', $dto->replyToName);
        $this->assertSame(0, $dto->forceReplyTo);
        $this->assertSame('', $dto->ccEmail);
        $this->assertSame('', $dto->ccName);
        $this->assertSame(0, $dto->forceCc);
        $this->assertSame('', $dto->bccEmail);
        $this->assertSame('', $dto->bccName);
        $this->assertSame(0, $dto->forceBcc);
    }

    public function testFromArrayWithPartialData(): void
    {
        $data = [
            SettingKeys::REPLY_TO_EMAIL => 'reply@example.com',
            SettingKeys::CC_EMAIL => 'cc@example.com',
        ];

        $dto = AdvancedSettingsDTO::fromArray($data);

        $this->assertSame('reply@example.com', $dto->replyToEmail);
        $this->assertSame('', $dto->replyToName);
        $this->assertSame(0, $dto->forceReplyTo);
        $this->assertSame('cc@example.com', $dto->ccEmail);
        $this->assertSame('', $dto->ccName);
        $this->assertSame(0, $dto->forceCc);
        $this->assertSame('', $dto->bccEmail);
    }

    public function testFromArrayWithIntegerStrings(): void
    {
        $data = [
            SettingKeys::FORCE_REPLY_TO => 1,
            SettingKeys::FORCE_CC => 0,
            SettingKeys::FORCE_BCC =>1,
        ];

        $dto = AdvancedSettingsDTO::fromArray($data);

        $this->assertSame(1, $dto->forceReplyTo);
        $this->assertSame(0, $dto->forceCc);
        $this->assertSame(1, $dto->forceBcc);
    }

    public function testFromRequestWithCompleteData(): void
    {
        $requestData = [
            SettingKeys::REPLY_TO_EMAIL => 'reply@example.com',
            SettingKeys::REPLY_TO_NAME => 'Reply Name',
            SettingKeys::FORCE_REPLY_TO => '1',
            SettingKeys::CC_EMAIL => 'cc@example.com',
            SettingKeys::CC_NAME => 'CC Name',
            SettingKeys::FORCE_CC => '1',
            SettingKeys::BCC_EMAIL => 'bcc@example.com',
            SettingKeys::BCC_NAME => 'BCC Name',
            SettingKeys::FORCE_BCC => '1',
        ];

        $request = new RequestHandler($requestData);
        $dto = AdvancedSettingsDTO::fromRequest($request);

        $this->assertSame('reply@example.com', $dto->replyToEmail);
        $this->assertSame('Reply Name', $dto->replyToName);
        $this->assertSame(1, $dto->forceReplyTo);
        $this->assertSame('cc@example.com', $dto->ccEmail);
        $this->assertSame('CC Name', $dto->ccName);
        $this->assertSame(1, $dto->forceCc);
        $this->assertSame('bcc@example.com', $dto->bccEmail);
        $this->assertSame('BCC Name', $dto->bccName);
        $this->assertSame(1, $dto->forceBcc);
    }

    public function testFromRequestWithEmptyData(): void
    {
        $request = new RequestHandler([]);
        $dto = AdvancedSettingsDTO::fromRequest($request);

        $this->assertSame('', $dto->replyToEmail);
        $this->assertSame('', $dto->replyToName);
        $this->assertSame(0, $dto->forceReplyTo);
        $this->assertSame('', $dto->ccEmail);
        $this->assertSame('', $dto->ccName);
        $this->assertSame(0, $dto->forceCc);
        $this->assertSame('', $dto->bccEmail);
        $this->assertSame('', $dto->bccName);
        $this->assertSame(0, $dto->forceBcc);
    }

    public function testFromRequestWithPartialData(): void
    {
        $requestData = [
            SettingKeys::REPLY_TO_EMAIL => 'reply@example.com',
            SettingKeys::FORCE_REPLY_TO => '1',
            SettingKeys::BCC_EMAIL => 'bcc@example.com',
        ];

        $request = new RequestHandler($requestData);
        $dto = AdvancedSettingsDTO::fromRequest($request);

        $this->assertSame('reply@example.com', $dto->replyToEmail);
        $this->assertSame('', $dto->replyToName);
        $this->assertSame(1, $dto->forceReplyTo);
        $this->assertSame('', $dto->ccEmail);
        $this->assertSame('', $dto->ccName);
        $this->assertSame(0, $dto->forceCc);
        $this->assertSame('bcc@example.com', $dto->bccEmail);
        $this->assertSame('', $dto->bccName);
        $this->assertSame(0, $dto->forceBcc);
    }

    public function testToArrayReturnsCompleteArray(): void
    {
        $dto = new AdvancedSettingsDTO(
            'reply@example.com',
            'Reply Name',
            1,
            'cc@example.com',
            'CC Name',
            1,
            'bcc@example.com',
            'BCC Name',
            1
        );

        $array = $dto->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey(SettingKeys::REPLY_TO_EMAIL, $array);
        $this->assertArrayHasKey(SettingKeys::REPLY_TO_NAME, $array);
        $this->assertArrayHasKey(SettingKeys::FORCE_REPLY_TO, $array);
        $this->assertArrayHasKey(SettingKeys::CC_EMAIL, $array);
        $this->assertArrayHasKey(SettingKeys::CC_NAME, $array);
        $this->assertArrayHasKey(SettingKeys::FORCE_CC, $array);
        $this->assertArrayHasKey(SettingKeys::BCC_EMAIL, $array);
        $this->assertArrayHasKey(SettingKeys::BCC_NAME, $array);
        $this->assertArrayHasKey(SettingKeys::FORCE_BCC, $array);

        $this->assertSame('reply@example.com', $array[SettingKeys::REPLY_TO_EMAIL]);
        $this->assertSame('Reply Name', $array[SettingKeys::REPLY_TO_NAME]);
        $this->assertSame(1, $array[SettingKeys::FORCE_REPLY_TO]);
        $this->assertSame('cc@example.com', $array[SettingKeys::CC_EMAIL]);
        $this->assertSame('CC Name', $array[SettingKeys::CC_NAME]);
        $this->assertSame(1, $array[SettingKeys::FORCE_CC]);
        $this->assertSame('bcc@example.com', $array[SettingKeys::BCC_EMAIL]);
        $this->assertSame('BCC Name', $array[SettingKeys::BCC_NAME]);
        $this->assertSame(1, $array[SettingKeys::FORCE_BCC]);
    }

    public function testToArrayWithDefaultValues(): void
    {
        $dto = new AdvancedSettingsDTO();
        $array = $dto->toArray();

        $this->assertSame('', $array[SettingKeys::REPLY_TO_EMAIL]);
        $this->assertSame('', $array[SettingKeys::REPLY_TO_NAME]);
        $this->assertSame(0, $array[SettingKeys::FORCE_REPLY_TO]);
        $this->assertSame('', $array[SettingKeys::CC_EMAIL]);
        $this->assertSame('', $array[SettingKeys::CC_NAME]);
        $this->assertSame(0, $array[SettingKeys::FORCE_CC]);
        $this->assertSame('', $array[SettingKeys::BCC_EMAIL]);
        $this->assertSame('', $array[SettingKeys::BCC_NAME]);
        $this->assertSame(0, $array[SettingKeys::FORCE_BCC]);
    }

    public function testRoundTripConversionFromArrayToArray(): void
    {
        $originalData = [
            SettingKeys::REPLY_TO_EMAIL => 'reply@example.com',
            SettingKeys::REPLY_TO_NAME => 'Reply Name',
            SettingKeys::FORCE_REPLY_TO => 1,
            SettingKeys::CC_EMAIL => 'cc@example.com',
            SettingKeys::CC_NAME => 'CC Name',
            SettingKeys::FORCE_CC => 1,
            SettingKeys::BCC_EMAIL => 'bcc@example.com',
            SettingKeys::BCC_NAME => 'BCC Name',
            SettingKeys::FORCE_BCC => 1,
        ];

        $dto = AdvancedSettingsDTO::fromArray($originalData);
        $resultData = $dto->toArray();

        $this->assertEquals($originalData, $resultData);
    }

    public function testRoundTripConversionFromRequestToArray(): void
    {
        $requestData = [
            SettingKeys::REPLY_TO_EMAIL => 'reply@example.com',
            SettingKeys::REPLY_TO_NAME => 'Reply Name',
            SettingKeys::FORCE_REPLY_TO => '1',
            SettingKeys::CC_EMAIL => 'cc@example.com',
            SettingKeys::CC_NAME => 'CC Name',
            SettingKeys::FORCE_CC => '1',
            SettingKeys::BCC_EMAIL => 'bcc@example.com',
            SettingKeys::BCC_NAME => 'BCC Name',
            SettingKeys::FORCE_BCC => '1',
        ];

        $request = new RequestHandler($requestData);
        $dto = AdvancedSettingsDTO::fromRequest($request);
        $resultArray = $dto->toArray();

        $this->assertSame('reply@example.com', $resultArray[SettingKeys::REPLY_TO_EMAIL]);
        $this->assertSame('Reply Name', $resultArray[SettingKeys::REPLY_TO_NAME]);
        $this->assertSame(1, $resultArray[SettingKeys::FORCE_REPLY_TO]);
        $this->assertSame('cc@example.com', $resultArray[SettingKeys::CC_EMAIL]);
        $this->assertSame('CC Name', $resultArray[SettingKeys::CC_NAME]);
        $this->assertSame(1, $resultArray[SettingKeys::FORCE_CC]);
        $this->assertSame('bcc@example.com', $resultArray[SettingKeys::BCC_EMAIL]);
        $this->assertSame('BCC Name', $resultArray[SettingKeys::BCC_NAME]);
        $this->assertSame(1, $resultArray[SettingKeys::FORCE_BCC]);
    }

    public function testFromArrayHandlesNullValues(): void
    {
        $data = [
            SettingKeys::REPLY_TO_EMAIL => null,
            SettingKeys::REPLY_TO_NAME => null,
            SettingKeys::FORCE_REPLY_TO => null,
        ];

        $dto = AdvancedSettingsDTO::fromArray($data);

        $this->assertSame('', $dto->replyToEmail);
        $this->assertSame('', $dto->replyToName);
        $this->assertSame(0, $dto->forceReplyTo);
    }

    public function testModifyPropertiesAfterCreation(): void
    {
        $dto = new AdvancedSettingsDTO();

        $dto->replyToEmail = 'new-reply@example.com';
        $dto->replyToName = 'New Reply Name';
        $dto->forceReplyTo = 1;

        $this->assertSame('new-reply@example.com', $dto->replyToEmail);
        $this->assertSame('New Reply Name', $dto->replyToName);
        $this->assertSame(1, $dto->forceReplyTo);
    }

    public function testToArrayAfterModification(): void
    {
        $dto = new AdvancedSettingsDTO();

        $dto->replyToEmail = 'modified@example.com';
        $dto->forceReplyTo = 1;
        $dto->ccEmail = 'cc-modified@example.com';
        $dto->forceCc = 1;

        $array = $dto->toArray();

        $this->assertSame('modified@example.com', $array[SettingKeys::REPLY_TO_EMAIL]);
        $this->assertSame(1, $array[SettingKeys::FORCE_REPLY_TO]);
        $this->assertSame('cc-modified@example.com', $array[SettingKeys::CC_EMAIL]);
        $this->assertSame(1, $array[SettingKeys::FORCE_CC]);
    }
}