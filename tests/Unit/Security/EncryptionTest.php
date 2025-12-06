<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Security;

use Emercury\Smtp\Security\Encryption;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;

class EncryptionTest extends TestCase
{
    private Encryption $encryption;

    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('wp_salt')->justReturn('test-salt-value-for-testing-12345678');

        $this->encryption = new Encryption();
    }

    public function testEncryptReturnsNonEmptyString(): void
    {
        $data = 'sensitive-password';
        $encrypted = $this->encryption->encrypt($data);

        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals($data, $encrypted);
    }

    public function testEncryptReturnsEmptyStringForEmptyInput(): void
    {
        $result = $this->encryption->encrypt('');
        $this->assertEmpty($result);
    }

    public function testDecryptReturnsOriginalData(): void
    {
        $originalData = 'my-secret-password';

        $encrypted = $this->encryption->encrypt($originalData);
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertEquals($originalData, $decrypted);
    }

    public function testDecryptReturnsEmptyStringForEmptyInput(): void
    {
        $result = $this->encryption->decrypt('');
        $this->assertEmpty($result);
    }

    public function testDecryptHandlesInvalidData(): void
    {
        $result = $this->encryption->decrypt('invalid-encrypted-data');
        $this->assertEmpty($result);
    }

    public function testEncryptionIsConsistent(): void
    {
        $data = 'test-data';

        $encrypted1 = $this->encryption->encrypt($data);
        $encrypted2 = $this->encryption->encrypt($data);

        $this->assertEquals($encrypted1, $encrypted2);
    }
}