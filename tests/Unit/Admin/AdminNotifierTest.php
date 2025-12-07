<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Admin;

use Emercury\Smtp\Admin\AdminNotifier;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;

class AdminNotifierTest extends TestCase
{
    private AdminNotifier $notifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notifier = new AdminNotifier();
    }

    public function testAddSuccessCallsAddSettingsError(): void
    {
        Functions\expect('add_settings_error')
            ->once()
            ->with(
                'em-smtp-relay-success',
                'settings_updated',
                'Success message',
                'success'
            );

        $this->notifier->addSuccess('Success message');
    }

    public function testAddErrorCallsAddSettingsError(): void
    {
        Functions\expect('add_settings_error')
            ->once()
            ->with(
                'em-smtp-relay-error',
                'settings_updated',
                'Error message',
                'error'
            );

        $this->notifier->addError('Error message');
    }

    public function testAddErrorsCallsAddSettingsErrorForEachMessage(): void
    {
        $messages = [
            'Error 1',
            'Error 2',
            'Error 3'
        ];

        Functions\expect('add_settings_error')
            ->times(3)
            ->with(
                'em-smtp-relay-error',
                'settings_updated',
                \Mockery::type('string'),
                'error'
            );

        $this->notifier->addErrors($messages);
    }

    public function testAddErrorsWithEmptyArray(): void
    {
        Functions\expect('add_settings_error')
            ->never();

        $this->notifier->addErrors([]);
    }

    public function testAddErrorsWithSingleMessage(): void
    {
        Functions\expect('add_settings_error')
            ->once()
            ->with(
                'em-smtp-relay-error',
                'settings_updated',
                'Single error',
                'error'
            );

        $this->notifier->addErrors(['Single error']);
    }

    public function testDisplayNoticesCallsSettingsErrorsForSuccess(): void
    {
        Functions\expect('settings_errors')
            ->once()
            ->with('em-smtp-relay-success');

        Functions\expect('settings_errors')
            ->once()
            ->with('em-smtp-relay-error');

        $this->notifier->displayNotices();
    }

    public function testDisplayNoticesCallsSettingsErrorsForError(): void
    {
        Functions\expect('settings_errors')
            ->once()
            ->with('em-smtp-relay-success');

        Functions\expect('settings_errors')
            ->once()
            ->with('em-smtp-relay-error');

        $this->notifier->displayNotices();
    }

    public function testAddSuccessWithHtmlContent(): void
    {
        Functions\expect('add_settings_error')
            ->once()
            ->with(
                'em-smtp-relay-success',
                'settings_updated',
                '<strong>Success!</strong> Settings saved.',
                'success'
            );

        $this->notifier->addSuccess('<strong>Success!</strong> Settings saved.');
    }

    public function testAddErrorWithHtmlContent(): void
    {
        Functions\expect('add_settings_error')
            ->once()
            ->with(
                'em-smtp-relay-error',
                'settings_updated',
                '<strong>Error:</strong> Invalid configuration.',
                'error'
            );

        $this->notifier->addError('<strong>Error:</strong> Invalid configuration.');
    }

    public function testAddSuccessWithEmptyMessage(): void
    {
        Functions\expect('add_settings_error')
            ->once()
            ->with(
                'em-smtp-relay-success',
                'settings_updated',
                '',
                'success'
            );

        $this->notifier->addSuccess('');
    }

    public function testAddErrorWithEmptyMessage(): void
    {
        Functions\expect('add_settings_error')
            ->once()
            ->with(
                'em-smtp-relay-error',
                'settings_updated',
                '',
                'error'
            );

        $this->notifier->addError('');
    }

    public function testMultipleSuccessMessages(): void
    {
        Functions\expect('add_settings_error')
            ->times(3)
            ->with(
                'em-smtp-relay-success',
                'settings_updated',
                \Mockery::type('string'),
                'success'
            );

        $this->notifier->addSuccess('Message 1');
        $this->notifier->addSuccess('Message 2');
        $this->notifier->addSuccess('Message 3');
    }

    public function testMultipleErrorMessages(): void
    {
        Functions\expect('add_settings_error')
            ->times(3)
            ->with(
                'em-smtp-relay-error',
                'settings_updated',
                \Mockery::type('string'),
                'error'
            );

        $this->notifier->addError('Error 1');
        $this->notifier->addError('Error 2');
        $this->notifier->addError('Error 3');
    }

    public function testMixedSuccessAndErrorMessages(): void
    {
        Functions\expect('add_settings_error')
            ->once()
            ->with(
                'em-smtp-relay-success',
                'settings_updated',
                'Success message',
                'success'
            );

        Functions\expect('add_settings_error')
            ->once()
            ->with(
                'em-smtp-relay-error',
                'settings_updated',
                'Error message',
                'error'
            );

        $this->notifier->addSuccess('Success message');
        $this->notifier->addError('Error message');
    }

    public function testAddErrorsWithMixedContent(): void
    {
        $messages = [
            'Plain error',
            '<strong>HTML error</strong>',
            'Error with <em>emphasis</em>'
        ];

        Functions\expect('add_settings_error')
            ->times(3)
            ->with(
                'em-smtp-relay-error',
                'settings_updated',
                \Mockery::type('string'),
                'error'
            );

        $this->notifier->addErrors($messages);
    }

    public function testDisplayNoticesCanBeCalledMultipleTimes(): void
    {
        Functions\expect('settings_errors')
            ->times(4);

        $this->notifier->displayNotices();
        $this->notifier->displayNotices();
    }

    public function testAddSuccessWithLongMessage(): void
    {
        $longMessage = str_repeat('Success! ', 100);

        Functions\expect('add_settings_error')
            ->once()
            ->with(
                'em-smtp-relay-success',
                'settings_updated',
                $longMessage,
                'success'
            );

        $this->notifier->addSuccess($longMessage);
    }

    public function testAddErrorWithLongMessage(): void
    {
        $longMessage = str_repeat('Error! ', 100);

        Functions\expect('add_settings_error')
            ->once()
            ->with(
                'em-smtp-relay-error',
                'settings_updated',
                $longMessage,
                'error'
            );

        $this->notifier->addError($longMessage);
    }

    public function testAddErrorsPreservesOrder(): void
    {
        $messages = ['First', 'Second', 'Third'];
        $callOrder = [];

        Functions\expect('add_settings_error')
            ->times(3)
            ->andReturnUsing(function($group, $code, $message, $type) use (&$callOrder) {
                $callOrder[] = $message;
            });

        $this->notifier->addErrors($messages);

        $this->assertEquals($messages, $callOrder);
    }

    public function testAddSuccessWithSpecialCharacters(): void
    {
        Functions\expect('add_settings_error')
            ->once()
            ->with(
                'em-smtp-relay-success',
                'settings_updated',
                'Success: <>&"\'',
                'success'
            );

        $this->notifier->addSuccess('Success: <>&"\'');
    }

    public function testAddErrorWithSpecialCharacters(): void
    {
        Functions\expect('add_settings_error')
            ->once()
            ->with(
                'em-smtp-relay-error',
                'settings_updated',
                'Error: <>&"\'',
                'error'
            );

        $this->notifier->addError('Error: <>&"\'');
    }

    public function testAddErrorsWithDuplicateMessages(): void
    {
        $messages = ['Error', 'Error', 'Error'];

        Functions\expect('add_settings_error')
            ->times(3)
            ->with(
                'em-smtp-relay-error',
                'settings_updated',
                'Error',
                'error'
            );

        $this->notifier->addErrors($messages);
    }

    public function testNotifierUsesCorrectPrefix(): void
    {
        Functions\expect('add_settings_error')
            ->once()
            ->with(
                \Mockery::on(function($arg) {
                    return strpos($arg, 'em-smtp-relay-') === 0;
                }),
                'settings_updated',
                'Test message',
                'success'
            );

        $this->notifier->addSuccess('Test message');
    }

    public function testDisplayNoticesUsesCorrectPrefixes(): void
    {
        Functions\expect('settings_errors')
            ->once()
            ->with(\Mockery::on(function($arg) {
                return $arg === 'em-smtp-relay-success';
            }));

        Functions\expect('settings_errors')
            ->once()
            ->with(\Mockery::on(function($arg) {
                return $arg === 'em-smtp-relay-error';
            }));

        $this->notifier->displayNotices();
    }
}