<?php

declare(strict_types=1);

namespace Emercury\Smtp\Admin;

class AdminNotifier
{
    private const NOTICE_PREFIX = 'em-smtp-relay-';

    public function __construct()
    {
        add_action('admin_notices', [$this, 'displayNotices']);
    }

    public function addSuccess(string $message): void
    {
        $this->addNotice('success', $message);
    }

    public function addError(string $message): void
    {
        $this->addNotice('error', $message);
    }

    public function addErrors(array $messages): void
    {
        foreach ($messages as $message) {
            $this->addNotice('error', $message);
        }
    }

    private function addNotice(string $type, string $message): void
    {
        add_settings_error(
            self::NOTICE_PREFIX . $type,
            'settings_updated',
            $message,
            $type
        );
    }

    public function displayNotices(): void
    {
        settings_errors(self::NOTICE_PREFIX . 'success');
        settings_errors(self::NOTICE_PREFIX . 'error');
    }
}