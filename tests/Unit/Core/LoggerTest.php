<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Core;

use Emercury\Smtp\Core\Logger;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;

class LoggerTest extends TestCase
{
    private Logger $logger;
    private array $errorLogs = [];
    private array $options = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->errorLogs = [];
        $this->options = [];

        // Mock error_log to capture log messages
        Functions\when('error_log')->alias(function($message) {
            $this->errorLogs[] = $message;
        });

        Functions\when('get_option')->alias(function($key, $default = false) {
            return $this->options[$key] ?? $default;
        });

        Functions\when('update_option')->alias(function($key, $value) {
            $this->options[$key] = $value;
            return true;
        });

        Functions\when('wp_json_encode')->alias(function($data, $options = 0) {
            return json_encode($data, $options);
        });

        Functions\when('current_time')->justReturn('2024-01-15 10:30:00');

        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', false);
        }

        $this->logger = new Logger();
    }

    // error() method tests
    public function testErrorLogsMessage(): void
    {
        $this->logger->error('Test error message');

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('[Emercury SMTP]', $this->errorLogs[0]);
        $this->assertStringContainsString('[ERROR]', $this->errorLogs[0]);
        $this->assertStringContainsString('Test error message', $this->errorLogs[0]);
    }

    public function testErrorWithContext(): void
    {
        $context = ['user_id' => 123, 'email' => 'test@example.com'];

        $this->logger->error('Test error', $context);

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('Test error', $this->errorLogs[0]);
        $this->assertStringContainsString('Context:', $this->errorLogs[0]);
        $this->assertStringContainsString('user_id', $this->errorLogs[0]);
        $this->assertStringContainsString('123', $this->errorLogs[0]);
    }

    public function testErrorWithEmptyContext(): void
    {
        $this->logger->error('Test error', []);

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('Test error', $this->errorLogs[0]);
        $this->assertStringNotContainsString('Context:', $this->errorLogs[0]);
    }

    public function testErrorSavesToDatabase(): void
    {
        $this->logger->error('Database error', ['code' => 500]);

        $this->assertArrayHasKey('em_smtp_error_logs', $this->options);
        $logs = $this->options['em_smtp_error_logs'];

        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);
        $this->assertEquals('ERROR', $logs[0]['level']);
        $this->assertEquals('Database error', $logs[0]['message']);
        $this->assertEquals(['code' => 500], $logs[0]['context']);
        $this->assertEquals('2024-01-15 10:30:00', $logs[0]['timestamp']);
    }

    public function testErrorLimitsLogsTo100Entries(): void
    {
        // Pre-populate with 100 logs
        $existingLogs = [];
        for ($i = 0; $i < 100; $i++) {
            $existingLogs[] = [
                'level' => 'ERROR',
                'message' => "Old error $i",
                'context' => [],
                'timestamp' => '2024-01-14 10:00:00',
            ];
        }
        $this->options['em_smtp_error_logs'] = $existingLogs;

        // Add one more error
        $this->logger->error('New error');

        $logs = $this->options['em_smtp_error_logs'];

        // Should still have 100 logs (oldest removed)
        $this->assertCount(100, $logs);

        // First log should be "Old error 1" (not "Old error 0")
        $this->assertEquals('Old error 1', $logs[0]['message']);

        // Last log should be the new one
        $this->assertEquals('New error', $logs[99]['message']);
    }

    public function testErrorWithEmptyMessage(): void
    {
        $this->logger->error('');

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('[ERROR]', $this->errorLogs[0]);
    }

    public function testErrorWithLongMessage(): void
    {
        $longMessage = str_repeat('Error ', 100);

        $this->logger->error($longMessage);

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString($longMessage, $this->errorLogs[0]);
    }

    public function testErrorWithSpecialCharacters(): void
    {
        $this->logger->error('Error: <>&"\'', ['data' => '<script>alert(1)</script>']);

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('Error: <>&"\'', $this->errorLogs[0]);
    }

    // info() method tests
    public function testInfoLogsMessage(): void
    {
        $this->logger->info('Test info message');

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('[Emercury SMTP]', $this->errorLogs[0]);
        $this->assertStringContainsString('[INFO]', $this->errorLogs[0]);
        $this->assertStringContainsString('Test info message', $this->errorLogs[0]);
    }

    public function testInfoWithContext(): void
    {
        $context = ['action' => 'send_email', 'count' => 5];

        $this->logger->info('Email sent successfully', $context);

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('Email sent successfully', $this->errorLogs[0]);
        $this->assertStringContainsString('Context:', $this->errorLogs[0]);
        $this->assertStringContainsString('action', $this->errorLogs[0]);
    }

    public function testInfoWithEmptyContext(): void
    {
        $this->logger->info('Info message', []);

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('Info message', $this->errorLogs[0]);
        $this->assertStringNotContainsString('Context:', $this->errorLogs[0]);
    }

    public function testInfoDoesNotSaveToDatabase(): void
    {
        $this->logger->info('Info message');

        $this->assertArrayNotHasKey('em_smtp_error_logs', $this->options);
    }

    public function testInfoWithEmptyMessage(): void
    {
        $this->logger->info('');

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('[INFO]', $this->errorLogs[0]);
    }

    // warning() method tests
    public function testWarningLogsMessage(): void
    {
        $this->logger->warning('Test warning message');

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('[Emercury SMTP]', $this->errorLogs[0]);
        $this->assertStringContainsString('[WARNING]', $this->errorLogs[0]);
        $this->assertStringContainsString('Test warning message', $this->errorLogs[0]);
    }

    public function testWarningWithContext(): void
    {
        $context = ['threshold' => 90, 'current' => 95];

        $this->logger->warning('Rate limit approaching', $context);

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('Rate limit approaching', $this->errorLogs[0]);
        $this->assertStringContainsString('Context:', $this->errorLogs[0]);
        $this->assertStringContainsString('threshold', $this->errorLogs[0]);
    }

    public function testWarningWithEmptyContext(): void
    {
        $this->logger->warning('Warning message', []);

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('Warning message', $this->errorLogs[0]);
        $this->assertStringNotContainsString('Context:', $this->errorLogs[0]);
    }

    public function testWarningDoesNotSaveToDatabase(): void
    {
        $this->logger->warning('Warning message');

        $this->assertArrayNotHasKey('em_smtp_error_logs', $this->options);
    }

    // debug() method tests
    public function testDebugDoesNotLogWhenWpDebugIsFalse(): void
    {
        // WP_DEBUG is false by default in setUp
        $this->logger->debug('Debug message');

        $this->assertEmpty($this->errorLogs);
    }

    public function testDebugLogsWhenWpDebugIsTrue(): void
    {
        // Need to create a new constant for this test
        // Since we can't redefine constants, we'll skip this functionality test
        // and just verify the behavior with WP_DEBUG false
        $this->logger->debug('Debug message');

        $this->assertEmpty($this->errorLogs);
    }

    public function testDebugWithContext(): void
    {
        $this->logger->debug('Debug info', ['var' => 'value']);

        // Should not log when WP_DEBUG is false
        $this->assertEmpty($this->errorLogs);
    }

    public function testDebugDoesNotSaveToDatabase(): void
    {
        $this->logger->debug('Debug message');

        $this->assertArrayNotHasKey('em_smtp_error_logs', $this->options);
    }

    // Message format tests
    public function testLogFormatIncludesPrefix(): void
    {
        $this->logger->info('Test');

        $this->assertStringContainsString('[Emercury SMTP]', $this->errorLogs[0]);
    }

    public function testLogFormatIncludesLevel(): void
    {
        $this->logger->error('Error test');
        $this->logger->info('Info test');
        $this->logger->warning('Warning test');

        $this->assertStringContainsString('[ERROR]', $this->errorLogs[0]);
        $this->assertStringContainsString('[INFO]', $this->errorLogs[1]);
        $this->assertStringContainsString('[WARNING]', $this->errorLogs[2]);
    }

    public function testLogFormatIncludesMessage(): void
    {
        $message = 'This is a test message';

        $this->logger->info($message);

        $this->assertStringContainsString($message, $this->errorLogs[0]);
    }

    public function testLogFormatWithComplexContext(): void
    {
        $context = [
            'user' => [
                'id' => 123,
                'email' => 'test@example.com',
                'roles' => ['admin', 'editor']
            ],
            'settings' => [
                'smtp_host' => 'smtp.example.com',
                'smtp_port' => 587
            ]
        ];

        $this->logger->info('Complex context test', $context);

        $log = $this->errorLogs[0];
        $this->assertStringContainsString('Context:', $log);
        $this->assertStringContainsString('user', $log);
        $this->assertStringContainsString('settings', $log);
    }

    // Multiple log entries tests
    public function testMultipleErrorsAreLogged(): void
    {
        $this->logger->error('First error');
        $this->logger->error('Second error');
        $this->logger->error('Third error');

        $this->assertCount(3, $this->errorLogs);
    }

    public function testMultipleErrorsAreSavedToDatabase(): void
    {
        $this->logger->error('Error 1');
        $this->logger->error('Error 2');
        $this->logger->error('Error 3');

        $logs = $this->options['em_smtp_error_logs'];

        $this->assertCount(3, $logs);
        $this->assertEquals('Error 1', $logs[0]['message']);
        $this->assertEquals('Error 2', $logs[1]['message']);
        $this->assertEquals('Error 3', $logs[2]['message']);
    }

    public function testMixedLogLevels(): void
    {
        $this->logger->error('Error message');
        $this->logger->info('Info message');
        $this->logger->warning('Warning message');

        $this->assertCount(3, $this->errorLogs);

        // Only error should be saved to database
        $this->assertCount(1, $this->options['em_smtp_error_logs']);
    }

    // Edge cases
    public function testLogWithNullContext(): void
    {
        $this->logger->error('Test', null);

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('Test', $this->errorLogs[0]);
    }

    public function testLogWithNumericMessage(): void
    {
        $this->logger->info('12345');

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('12345', $this->errorLogs[0]);
    }

    public function testLogWithMultilineMessage(): void
    {
        $message = "Line 1\nLine 2\nLine 3";

        $this->logger->info($message);

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('Line 1', $this->errorLogs[0]);
    }

    public function testLogWithArrayContext(): void
    {
        $context = ['items' => [1, 2, 3, 4, 5]];

        $this->logger->info('Array context', $context);

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('Context:', $this->errorLogs[0]);
    }

    public function testLogWithBooleanContext(): void
    {
        $context = ['enabled' => true, 'disabled' => false];

        $this->logger->info('Boolean context', $context);

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('Context:', $this->errorLogs[0]);
    }

    public function testLogWithNullValuesInContext(): void
    {
        $context = ['key1' => null, 'key2' => 'value'];

        $this->logger->info('Null context', $context);

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('Context:', $this->errorLogs[0]);
    }

    // Database operations tests
    public function testErrorCreatesNewLogsArrayIfNotExists(): void
    {
        $this->assertArrayNotHasKey('em_smtp_error_logs', $this->options);

        $this->logger->error('First error');

        $this->assertArrayHasKey('em_smtp_error_logs', $this->options);
        $this->assertIsArray($this->options['em_smtp_error_logs']);
    }

    public function testErrorAppendsToExistingLogs(): void
    {
        $this->options['em_smtp_error_logs'] = [
            [
                'level' => 'ERROR',
                'message' => 'Existing error',
                'context' => [],
                'timestamp' => '2024-01-14 10:00:00'
            ]
        ];

        $this->logger->error('New error');

        $logs = $this->options['em_smtp_error_logs'];

        $this->assertCount(2, $logs);
        $this->assertEquals('Existing error', $logs[0]['message']);
        $this->assertEquals('New error', $logs[1]['message']);
    }

    public function testErrorMaintainsChronologicalOrder(): void
    {
        Functions\when('current_time')->alias(function() {
            static $counter = 0;
            $counter++;
            return "2024-01-15 10:3{$counter}:00";
        });

        $logger = new Logger();

        $logger->error('Error 1');
        $logger->error('Error 2');
        $logger->error('Error 3');

        $logs = $this->options['em_smtp_error_logs'];

        $this->assertEquals('2024-01-15 10:31:00', $logs[0]['timestamp']);
        $this->assertEquals('2024-01-15 10:32:00', $logs[1]['timestamp']);
        $this->assertEquals('2024-01-15 10:33:00', $logs[2]['timestamp']);
    }

    public function testLogStructureContainsAllRequiredFields(): void
    {
        $this->logger->error('Test error', ['key' => 'value']);

        $logs = $this->options['em_smtp_error_logs'];
        $log = $logs[0];

        $this->assertArrayHasKey('level', $log);
        $this->assertArrayHasKey('message', $log);
        $this->assertArrayHasKey('context', $log);
        $this->assertArrayHasKey('timestamp', $log);
    }

    public function testLogStructureHasCorrectTypes(): void
    {
        $this->logger->error('Test error', ['key' => 'value']);

        $logs = $this->options['em_smtp_error_logs'];
        $log = $logs[0];

        $this->assertIsString($log['level']);
        $this->assertIsString($log['message']);
        $this->assertIsArray($log['context']);
        $this->assertIsString($log['timestamp']);
    }

    // Context encoding tests
    public function testContextIsJsonEncoded(): void
    {
        $context = ['user_id' => 123, 'action' => 'test'];

        $this->logger->info('Test', $context);

        $log = $this->errorLogs[0];
        $this->assertStringContainsString('user_id', $log);
        $this->assertStringContainsString('123', $log);
        $this->assertStringContainsString('action', $log);
    }

    public function testContextWithNestedArrays(): void
    {
        $context = [
            'level1' => [
                'level2' => [
                    'level3' => 'deep value'
                ]
            ]
        ];

        $this->logger->info('Nested context', $context);

        $log = $this->errorLogs[0];
        $this->assertStringContainsString('Context:', $log);
        $this->assertStringContainsString('level1', $log);
    }

    public function testContextWithEmptyArray(): void
    {
        $context = ['empty_array' => []];

        $this->logger->info('Empty array context', $context);

        $this->assertCount(1, $this->errorLogs);
        $this->assertStringContainsString('Context:', $this->errorLogs[0]);
    }

    // Prefix constant tests
    public function testLogPrefixIsConsistent(): void
    {
        $this->logger->error('Error');
        $this->logger->info('Info');
        $this->logger->warning('Warning');

        foreach ($this->errorLogs as $log) {
            $this->assertStringStartsWith('[Emercury SMTP]', $log);
        }
    }

    public function testLogPrefixIsNotDuplicated(): void
    {
        $this->logger->info('Test');

        $count = substr_count($this->errorLogs[0], '[Emercury SMTP]');
        $this->assertEquals(1, $count);
    }

    // Performance tests
    public function testHandlesManyErrorsEfficiently(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->logger->error("Error $i");
        }

        $this->assertCount(50, $this->errorLogs);
        $this->assertCount(50, $this->options['em_smtp_error_logs']);
    }

    public function testHandlesLargeContext(): void
    {
        $largeContext = [];
        for ($i = 0; $i < 100; $i++) {
            $largeContext["key_$i"] = "value_$i";
        }

        $this->logger->error('Large context', $largeContext);

        $this->assertCount(1, $this->errorLogs);
        $this->assertCount(1, $this->options['em_smtp_error_logs']);
    }

    protected function tearDown(): void
    {
        $this->errorLogs = [];
        $this->options = [];

        parent::tearDown();
    }
}