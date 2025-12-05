<?php
/**
 * PSR-4 Autoloader для Emercury SMTP
 */

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'Emercury\\Smtp\\';
    $baseDir = __DIR__ . '/src/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});