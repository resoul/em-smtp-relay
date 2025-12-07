<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Security;

use Emercury\Smtp\Security\NonceManager;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;

class NonceManagerTest extends TestCase
{
    private NonceManager $nonceManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nonceManager = new NonceManager();
    }

    public function testVerifyReturnsTrueWhenNonceIsValid(): void
    {
        $_REQUEST['_wpnonce'] = 'valid_nonce';

        Functions\expect('wp_verify_nonce')
            ->once()
            ->with('valid_nonce', 'test_action')
            ->andReturn(1);

        $result = $this->nonceManager->verify('test_action');

        $this->assertTrue($result);
    }

    public function testVerifyReturnsFalseWhenNonceIsInvalid(): void
    {
        $_REQUEST['_wpnonce'] = 'invalid_nonce';

        Functions\expect('wp_verify_nonce')
            ->once()
            ->with('invalid_nonce', 'test_action')
            ->andReturn(false);

        $result = $this->nonceManager->verify('test_action');

        $this->assertFalse($result);
    }

    public function testVerifyReturnsFalseWhenNonceIsMissing(): void
    {
        unset($_REQUEST['_wpnonce']);

        Functions\expect('wp_verify_nonce')
            ->once()
            ->with('', 'test_action')
            ->andReturn(false);

        $result = $this->nonceManager->verify('test_action');

        $this->assertFalse($result);
    }

    public function testVerifyWithCustomNonceName(): void
    {
        $_REQUEST['custom_nonce'] = 'test_nonce';

        Functions\expect('wp_verify_nonce')
            ->once()
            ->with('test_nonce', 'test_action')
            ->andReturn(1);

        $result = $this->nonceManager->verify('test_action', 'custom_nonce');

        $this->assertTrue($result);
    }

    public function testVerifyWithCapabilityReturnsTrueWhenUserHasCapabilityAndNonceIsValid(): void
    {
        $_REQUEST['_wpnonce'] = 'valid_nonce';

        Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(true);

        Functions\expect('wp_verify_nonce')
            ->once()
            ->with('valid_nonce', 'test_action')
            ->andReturn(1);

        $result = $this->nonceManager->verifyWithCapability('test_action');

        $this->assertTrue($result);
    }

    public function testVerifyWithCapabilityReturnsFalseWhenUserLacksCapability(): void
    {
        Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(false);

        Functions\expect('wp_verify_nonce')
            ->never();

        $result = $this->nonceManager->verifyWithCapability('test_action');

        $this->assertFalse($result);
    }

    public function testVerifyWithCapabilityReturnsFalseWhenNonceIsInvalidButUserHasCapability(): void
    {
        $_REQUEST['_wpnonce'] = 'invalid_nonce';

        Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(true);

        Functions\expect('wp_verify_nonce')
            ->once()
            ->with('invalid_nonce', 'test_action')
            ->andReturn(false);

        $result = $this->nonceManager->verifyWithCapability('test_action');

        $this->assertFalse($result);
    }

    public function testVerifyWithCapabilityWithCustomCapability(): void
    {
        $_REQUEST['_wpnonce'] = 'valid_nonce';

        Functions\expect('current_user_can')
            ->once()
            ->with('edit_posts')
            ->andReturn(true);

        Functions\expect('wp_verify_nonce')
            ->once()
            ->with('valid_nonce', 'test_action')
            ->andReturn(1);

        $result = $this->nonceManager->verifyWithCapability('test_action', 'edit_posts');

        $this->assertTrue($result);
    }

    public function testVerifyWithCapabilityWithCustomNonceName(): void
    {
        $_REQUEST['custom_nonce'] = 'valid_nonce';

        Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(true);

        Functions\expect('wp_verify_nonce')
            ->once()
            ->with('valid_nonce', 'test_action')
            ->andReturn(1);

        $result = $this->nonceManager->verifyWithCapability('test_action', 'manage_options', 'custom_nonce');

        $this->assertTrue($result);
    }

    public function testFieldReturnsNonceFieldHtml(): void
    {
        $expectedHtml = '<input type="hidden" name="_wpnonce" value="abc123" />';

        Functions\expect('wp_nonce_field')
            ->once()
            ->with('test_action', '_wpnonce', true, true)
            ->andReturn($expectedHtml);

        $result = $this->nonceManager->field('test_action');

        $this->assertEquals($expectedHtml, $result);
    }

    public function testFieldWithCustomParameters(): void
    {
        $expectedHtml = '<input type="hidden" name="custom_nonce" value="xyz789" />';

        Functions\expect('wp_nonce_field')
            ->once()
            ->with('custom_action', 'custom_nonce', false, false)
            ->andReturn($expectedHtml);

        $result = $this->nonceManager->field('custom_action', 'custom_nonce', false, false);

        $this->assertEquals($expectedHtml, $result);
    }

    protected function tearDown(): void
    {
        unset($_REQUEST['_wpnonce']);
        unset($_REQUEST['custom_nonce']);
        parent::tearDown();
    }
}