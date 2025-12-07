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

    // has() method tests
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

    public function testHasReturnsTrueForNullValue(): void
    {
        $request = new RequestHandler(['key' => null]);
        $this->assertTrue($request->has('key'));
    }

    public function testHasReturnsTrueForEmptyString(): void
    {
        $request = new RequestHandler(['key' => '']);
        $this->assertTrue($request->has('key'));
    }

    public function testHasReturnsTrueForZero(): void
    {
        $request = new RequestHandler(['key' => 0]);
        $this->assertTrue($request->has('key'));
    }

    public function testHasReturnsTrueForFalse(): void
    {
        $request = new RequestHandler(['key' => false]);
        $this->assertTrue($request->has('key'));
    }

    // getString() method tests
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

    public function testGetStringReturnsEmptyStringAsDefault(): void
    {
        $request = new RequestHandler([]);
        $this->assertEquals('', $request->getString('name'));
    }

    public function testGetStringCallsSanitizeTextField(): void
    {
        Functions\expect('sanitize_text_field')
            ->once()
            ->with('test value')
            ->andReturn('sanitized');

        $request = new RequestHandler(['key' => 'test value']);
        $result = $request->getString('key');

        $this->assertEquals('sanitized', $result);
    }

    public function testGetStringHandlesNumericValue(): void
    {
        $request = new RequestHandler(['key' => 123]);
        $result = $request->getString('key');
        $this->assertEquals('123', $result);
    }

    // getEmail() method tests
    public function testGetEmailReturnsValue(): void
    {
        $request = new RequestHandler(['email' => 'test@example.com']);
        $this->assertEquals('test@example.com', $request->getEmail('email'));
    }

    public function testGetEmailReturnsDefaultForMissingKey(): void
    {
        $request = new RequestHandler([]);
        $this->assertEquals('default@example.com', $request->getEmail('email', 'default@example.com'));
    }

    public function testGetEmailReturnsEmptyStringAsDefault(): void
    {
        $request = new RequestHandler([]);
        $this->assertEquals('', $request->getEmail('email'));
    }

    public function testGetEmailCallsSanitizeEmail(): void
    {
        Functions\expect('sanitize_email')
            ->once()
            ->with('test@example.com')
            ->andReturn('test@example.com');

        $request = new RequestHandler(['email' => 'test@example.com']);
        $result = $request->getEmail('email');

        $this->assertEquals('test@example.com', $result);
    }

    // getInt() method tests
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

    public function testGetIntReturnsZeroAsDefault(): void
    {
        $request = new RequestHandler([]);
        $this->assertSame(0, $request->getInt('count'));
    }

    public function testGetIntConvertsStringToInt(): void
    {
        $request = new RequestHandler(['count' => '100']);
        $this->assertSame(100, $request->getInt('count'));
    }

    public function testGetIntConvertsFloatToInt(): void
    {
        $request = new RequestHandler(['count' => 99.9]);
        $this->assertSame(99, $request->getInt('count'));
    }

    public function testGetIntConvertsNegativeStringToInt(): void
    {
        $request = new RequestHandler(['count' => '-50']);
        $this->assertSame(-50, $request->getInt('count'));
    }

    public function testGetIntReturnsZeroForNonNumericString(): void
    {
        $request = new RequestHandler(['count' => 'abc']);
        $this->assertSame(0, $request->getInt('count'));
    }

    public function testGetIntReturnsTrueAsOne(): void
    {
        $request = new RequestHandler(['count' => true]);
        $this->assertSame(1, $request->getInt('count'));
    }

    public function testGetIntReturnsFalseAsZero(): void
    {
        $request = new RequestHandler(['count' => false]);
        $this->assertSame(0, $request->getInt('count'));
    }

    // getBool() method tests
    public function testGetBoolReturnsBoolean(): void
    {
        $request = new RequestHandler(['active' => '1']);
        $this->assertTrue($request->getBool('active'));
    }

    public function testGetBoolReturnsDefaultForMissingKey(): void
    {
        $request = new RequestHandler([]);
        $this->assertTrue($request->getBool('active', true));
    }

    public function testGetBoolReturnsFalseAsDefault(): void
    {
        $request = new RequestHandler([]);
        $this->assertFalse($request->getBool('active'));
    }

    public function testGetBoolReturnsTrueForStringTrue(): void
    {
        $request = new RequestHandler(['active' => 'true']);
        $this->assertTrue($request->getBool('active'));
    }

    public function testGetBoolReturnsTrueForStringOne(): void
    {
        $request = new RequestHandler(['active' => '1']);
        $this->assertTrue($request->getBool('active'));
    }

    public function testGetBoolReturnsTrueForStringYes(): void
    {
        $request = new RequestHandler(['active' => 'yes']);
        $this->assertTrue($request->getBool('active'));
    }

    public function testGetBoolReturnsTrueForStringOn(): void
    {
        $request = new RequestHandler(['active' => 'on']);
        $this->assertTrue($request->getBool('active'));
    }

    public function testGetBoolReturnsFalseForStringFalse(): void
    {
        $request = new RequestHandler(['active' => 'false']);
        $this->assertFalse($request->getBool('active'));
    }

    public function testGetBoolReturnsFalseForStringZero(): void
    {
        $request = new RequestHandler(['active' => '0']);
        $this->assertFalse($request->getBool('active'));
    }

    public function testGetBoolReturnsFalseForStringNo(): void
    {
        $request = new RequestHandler(['active' => 'no']);
        $this->assertFalse($request->getBool('active'));
    }

    public function testGetBoolReturnsFalseForEmptyString(): void
    {
        $request = new RequestHandler(['active' => '']);
        $this->assertFalse($request->getBool('active'));
    }

    public function testGetBoolReturnsTrueForBooleanTrue(): void
    {
        $request = new RequestHandler(['active' => true]);
        $this->assertTrue($request->getBool('active'));
    }

    public function testGetBoolReturnsFalseForBooleanFalse(): void
    {
        $request = new RequestHandler(['active' => false]);
        $this->assertFalse($request->getBool('active'));
    }

    // getArray() method tests
    public function testGetArrayReturnsArray(): void
    {
        $data = ['items' => ['a', 'b', 'c']];
        $request = new RequestHandler($data);

        $result = $request->getArray('items');
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    public function testGetArrayReturnsDefaultForNonArray(): void
    {
        $request = new RequestHandler(['items' => 'not-array']);
        $result = $request->getArray('items', ['default']);

        $this->assertEquals(['default'], $result);
    }

    public function testGetArrayReturnsDefaultForMissingKey(): void
    {
        $request = new RequestHandler([]);
        $result = $request->getArray('items', ['default']);

        $this->assertEquals(['default'], $result);
    }

    public function testGetArrayReturnsEmptyArrayAsDefault(): void
    {
        $request = new RequestHandler([]);
        $result = $request->getArray('items');

        $this->assertEquals([], $result);
    }

    public function testGetArrayReturnsEmptyArray(): void
    {
        $request = new RequestHandler(['items' => []]);
        $result = $request->getArray('items');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetArrayReturnsAssociativeArray(): void
    {
        $data = ['items' => ['key1' => 'value1', 'key2' => 'value2']];
        $request = new RequestHandler($data);

        $result = $request->getArray('items');
        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $result);
    }

    public function testGetArrayReturnsNestedArray(): void
    {
        $data = ['items' => ['nested' => ['deep' => 'value']]];
        $request = new RequestHandler($data);

        $result = $request->getArray('items');
        $this->assertEquals(['nested' => ['deep' => 'value']], $result);
    }

    // getHtml() method tests
    public function testGetHtmlReturnsValue(): void
    {
        $request = new RequestHandler(['content' => '<p>Hello</p>']);
        $result = $request->getHtml('content');

        $this->assertEquals('<p>Hello</p>', $result);
    }

    public function testGetHtmlReturnsDefaultForMissingKey(): void
    {
        $request = new RequestHandler([]);
        $result = $request->getHtml('content', '<p>Default</p>');

        $this->assertEquals('<p>Default</p>', $result);
    }

    public function testGetHtmlReturnsEmptyStringAsDefault(): void
    {
        $request = new RequestHandler([]);
        $result = $request->getHtml('content');

        $this->assertEquals('', $result);
    }

    public function testGetHtmlCallsWpKsesPost(): void
    {
        Functions\expect('wp_kses_post')
            ->once()
            ->with('<p>Test</p>')
            ->andReturn('<p>Test</p>');

        $request = new RequestHandler(['content' => '<p>Test</p>']);
        $result = $request->getHtml('content');

        $this->assertEquals('<p>Test</p>', $result);
    }

    // getUrl() method tests
    public function testGetUrlReturnsValue(): void
    {
        $request = new RequestHandler(['url' => 'https://example.com']);
        $result = $request->getUrl('url');

        $this->assertEquals('https://example.com', $result);
    }

    public function testGetUrlReturnsDefaultForMissingKey(): void
    {
        $request = new RequestHandler([]);
        $result = $request->getUrl('url', 'https://default.com');

        $this->assertEquals('https://default.com', $result);
    }

    public function testGetUrlReturnsEmptyStringAsDefault(): void
    {
        $request = new RequestHandler([]);
        $result = $request->getUrl('url');

        $this->assertEquals('', $result);
    }

    public function testGetUrlCallsEscUrlRaw(): void
    {
        Functions\expect('esc_url_raw')
            ->once()
            ->with('https://example.com')
            ->andReturn('https://example.com');

        $request = new RequestHandler(['url' => 'https://example.com']);
        $result = $request->getUrl('url');

        $this->assertEquals('https://example.com', $result);
    }

    // getFile() method tests
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
        $this->assertEquals('application/pdf', $file['type']);
        $this->assertEquals('/tmp/phpXXXXXX', $file['tmp_name']);
        $this->assertEquals(UPLOAD_ERR_OK, $file['error']);
        $this->assertEquals(1024, $file['size']);
    }

    public function testGetFileReturnsNullForUploadError(): void
    {
        $files = [
            'attachment' => [
                'name' => 'test.pdf',
                'type' => 'application/pdf',
                'tmp_name' => '',
                'error' => UPLOAD_ERR_NO_FILE,
                'size' => 0
            ]
        ];

        $request = new RequestHandler([], $files);
        $this->assertNull($request->getFile('attachment'));
    }

    public function testGetFileReturnsNullForIniSizeError(): void
    {
        $files = [
            'attachment' => [
                'name' => 'test.pdf',
                'type' => 'application/pdf',
                'tmp_name' => '',
                'error' => UPLOAD_ERR_INI_SIZE,
                'size' => 0
            ]
        ];

        $request = new RequestHandler([], $files);
        $this->assertNull($request->getFile('attachment'));
    }

    public function testGetFileReturnsNullForFormSizeError(): void
    {
        $files = [
            'attachment' => [
                'name' => 'test.pdf',
                'type' => 'application/pdf',
                'tmp_name' => '',
                'error' => UPLOAD_ERR_FORM_SIZE,
                'size' => 0
            ]
        ];

        $request = new RequestHandler([], $files);
        $this->assertNull($request->getFile('attachment'));
    }

    public function testGetFileReturnsNullForPartialError(): void
    {
        $files = [
            'attachment' => [
                'name' => 'test.pdf',
                'type' => 'application/pdf',
                'tmp_name' => '',
                'error' => UPLOAD_ERR_PARTIAL,
                'size' => 0
            ]
        ];

        $request = new RequestHandler([], $files);
        $this->assertNull($request->getFile('attachment'));
    }

    // getFiles() method tests
    public function testGetFilesReturnsEmptyArrayForMissingFiles(): void
    {
        $request = new RequestHandler([], []);
        $result = $request->getFiles('attachments');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetFilesReturnsEmptyArrayForEmptyName(): void
    {
        $files = [
            'attachments' => [
                'name' => [''],
                'type' => [''],
                'tmp_name' => [''],
                'error' => [UPLOAD_ERR_NO_FILE],
                'size' => [0]
            ]
        ];

        $request = new RequestHandler([], $files);
        $result = $request->getFiles('attachments');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetFilesReturnsMultipleFiles(): void
    {
        $files = [
            'attachments' => [
                'name' => ['file1.pdf', 'file2.jpg'],
                'type' => ['application/pdf', 'image/jpeg'],
                'tmp_name' => ['/tmp/php1', '/tmp/php2'],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                'size' => [1024, 2048]
            ]
        ];

        $request = new RequestHandler([], $files);
        $result = $request->getFiles('attachments');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        $this->assertEquals('file1.pdf', $result[0]['name']);
        $this->assertEquals('application/pdf', $result[0]['type']);
        $this->assertEquals('/tmp/php1', $result[0]['tmp_name']);
        $this->assertEquals(UPLOAD_ERR_OK, $result[0]['error']);
        $this->assertEquals(1024, $result[0]['size']);

        $this->assertEquals('file2.jpg', $result[1]['name']);
        $this->assertEquals('image/jpeg', $result[1]['type']);
        $this->assertEquals('/tmp/php2', $result[1]['tmp_name']);
        $this->assertEquals(UPLOAD_ERR_OK, $result[1]['error']);
        $this->assertEquals(2048, $result[1]['size']);
    }

    public function testGetFilesSkipsFilesWithErrors(): void
    {
        $files = [
            'attachments' => [
                'name' => ['file1.pdf', 'file2.jpg', 'file3.png'],
                'type' => ['application/pdf', 'image/jpeg', 'image/png'],
                'tmp_name' => ['/tmp/php1', '', '/tmp/php3'],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK],
                'size' => [1024, 0, 3072]
            ]
        ];

        $request = new RequestHandler([], $files);
        $result = $request->getFiles('attachments');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('file1.pdf', $result[0]['name']);
        $this->assertEquals('file3.png', $result[1]['name']);
    }

    public function testGetFilesReturnsOnlySuccessfulUploads(): void
    {
        $files = [
            'attachments' => [
                'name' => ['file1.pdf', 'file2.jpg'],
                'type' => ['application/pdf', 'image/jpeg'],
                'tmp_name' => ['/tmp/php1', ''],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_INI_SIZE],
                'size' => [1024, 0]
            ]
        ];

        $request = new RequestHandler([], $files);
        $result = $request->getFiles('attachments');

        $this->assertCount(1, $result);
        $this->assertEquals('file1.pdf', $result[0]['name']);
    }

    // all() method tests
    public function testAllReturnsAllData(): void
    {
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        $request = new RequestHandler($data);

        $this->assertEquals($data, $request->all());
    }

    public function testAllReturnsEmptyArrayForNoData(): void
    {
        $request = new RequestHandler([]);
        $this->assertEquals([], $request->all());
    }

    public function testAllReturnsComplexDataStructure(): void
    {
        $data = [
            'string' => 'value',
            'int' => 42,
            'bool' => true,
            'array' => ['nested' => 'data'],
            'null' => null
        ];

        $request = new RequestHandler($data);
        $this->assertEquals($data, $request->all());
    }

    // Constructor tests
    public function testConstructorUsesPostByDefault(): void
    {
        $_POST = ['test' => 'value'];
        $request = new RequestHandler();

        $this->assertTrue($request->has('test'));
        $this->assertEquals('value', $request->getString('test'));
    }

    public function testConstructorUsesFilesbyDefault(): void
    {
        $_FILES = [
            'file' => [
                'name' => 'test.pdf',
                'type' => 'application/pdf',
                'tmp_name' => '/tmp/php',
                'error' => UPLOAD_ERR_OK,
                'size' => 1024
            ]
        ];

        $request = new RequestHandler();
        $file = $request->getFile('file');

        $this->assertNotNull($file);
        $this->assertEquals('test.pdf', $file['name']);
    }

    public function testConstructorAcceptsCustomData(): void
    {
        $customData = ['custom' => 'data'];
        $request = new RequestHandler($customData);

        $this->assertTrue($request->has('custom'));
        $this->assertEquals('data', $request->getString('custom'));
    }

    public function testConstructorAcceptsCustomFiles(): void
    {
        $customFiles = [
            'file' => [
                'name' => 'custom.pdf',
                'type' => 'application/pdf',
                'tmp_name' => '/tmp/custom',
                'error' => UPLOAD_ERR_OK,
                'size' => 2048
            ]
        ];

        $request = new RequestHandler([], $customFiles);
        $file = $request->getFile('file');

        $this->assertNotNull($file);
        $this->assertEquals('custom.pdf', $file['name']);
    }
}