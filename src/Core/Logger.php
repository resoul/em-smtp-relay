<?php

declare(strict_types=1);

namespace Emercury\Smtp\Core;

use Emercury\Smtp\Contracts\LoggerInterface;

class Logger implements LoggerInterface
{
    private const LOG_PREFIX = '[Emercury SMTP]';

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $this->log('DEBUG', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $logMessage = sprintf(
            '%s [%s] %s',
            self::LOG_PREFIX,
            $level,
            $message
        );

        if (!empty($context)) {
            $logMessage .= ' | Context: ' . wp_json_encode($context);
        }

        error_log($logMessage);

        if ($level === 'ERROR') {
            $this->saveToDatabase($level, $message, $context);
        }
    }

    private function saveToDatabase(string $level, string $message, array $context): void
    {
        $logs = get_option('em_smtp_error_logs', []);

        if (count($logs) >= 100) {
            array_shift($logs);
        }

        $logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => current_time('mysql'),
        ];

        update_option('em_smtp_error_logs', $logs);
    }
}