<?php

declare(strict_types=1);

namespace Emercury\Smtp\Contracts;

interface LoggerInterface
{
    /**
     * Log error message
     *
     * @param string $message Error message
     * @param array $context Additional context
     * @return void
     */
    public function error(string $message, array $context = []): void;

    /**
     * Log info message
     *
     * @param string $message Info message
     * @param array $context Additional context
     * @return void
     */
    public function info(string $message, array $context = []): void;

    /**
     * Log debug message
     *
     * @param string $message Debug message
     * @param array $context Additional context
     * @return void
     */
    public function debug(string $message, array $context = []): void;

    /**
     * Log warning message
     *
     * @param string $message Warning message
     * @param array $context Additional context
     * @return void
     */
    public function warning(string $message, array $context = []): void;
}