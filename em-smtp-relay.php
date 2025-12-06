<?php
/**
 * Plugin Name: Emercury SMTP Mail
 * Plugin URI: https://emercury.net
 * Description: Send emails from your WordPress site using Emercury SMTP.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Emercury Team
 * Author URI: https://emercury.net
 * Text Domain: em-smtp-relay
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('EM_SMTP_VERSION', '1.0.0');
define('EM_SMTP_FILE', __FILE__);
define('EM_SMTP_PATH', plugin_dir_path(__FILE__));
define('EM_SMTP_URL', plugin_dir_url(__FILE__));
define('EM_SMTP_BASENAME', plugin_basename(__FILE__));

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

add_action('plugins_loaded', function () {
    $container = new Emercury\Smtp\Core\Container();
    $plugin = new Emercury\Smtp\Core\Plugin($container);
    $plugin->init();
});
