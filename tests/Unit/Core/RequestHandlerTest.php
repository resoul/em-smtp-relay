<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Core;

use Emercury\Smtp\Core\RequestHandler;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;

class RequestHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('sanitize_email')->returnArg();
        Functions\when('wp_kses_post')->returnArg();
        Functions\when('esc_url_raw')->returnArg();
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $request = new RequestHandler(['key' => 'value']);
        $this->assertTrue($request->has('key'));
    }

    public function testHasReturnsFalseForNonExistingKey(): void
    {
        $request = new RequestHandler([]);
        $this->assertFalse($request->has('key'));
    }

    public function testGetStringReturnsValue(): void
    {
        $request = new RequestHandler(['name' => 'John']);
        $this->assertEquals('John', $request->getString('name'));
    }

    public function testGetStringReturnsDefaultForMissingKey(): void
    {
        $request = new RequestHandler([]);
        $this->assertEquals('default', $request->getString('name', 'default'));
    }

    public function testGetIntReturnsInteger(): void
    {
        $request = new RequestHandler(['count' => '42']);
        $this->assertSame(42, $request->getInt('count'));
    }

    public function testGetIntReturnsDefaultForMissingKey(): void
    {
        $request = new RequestHandler([]);
        $this->assertSame(10, $request->getInt('count', 10));
    }

    public function testGetBoolReturnsBoolean(): void
    {
        $request = new RequestHandler(['active' => '1']);
        $this->assertTrue($request->getBool('active'));
    }

    public function testGetArrayReturnsArray(): void
    {
        $data = ['items' => ['a', 'b', 'c']];
        $request = new RequestHandler($data);

        $result = $request->getArray('items');
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    public function testGetArrayReturnsDefaultForNonArray(): void
    {
        $request = new RequestHandler(['items' => 'not-array']);
        $result = $request->getArray('items', ['default']);

        $this->assertEquals(['default'], $result);
    }

    public function testGetFileReturnsNullForMissingFile(): void
    {
        $request = new RequestHandler([], []);
        $this->assertNull($request->getFile('attachment'));
    }

    public function testGetFileReturnsFileData(): void
    {
        $files = [
            'attachment' => [
                'name' => 'test.pdf',
                'type' => 'application/pdf',
                'tmp_name' => '/tmp/phpXXXXXX',
                'error' => UPLOAD_ERR_OK,
                'size' => 1024
            ]
        ];

        $request = new RequestHandler([], $files);
        $file = $request->getFile('attachment');

        $this->assertIsArray($file);
        $this->assertEquals('test.pdf', $file['name']);
    }

    public function testAllReturnsAllData(): void
    {
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        $request = new RequestHandler($data);

        $this->assertEquals($data, $request->all());
    }
}