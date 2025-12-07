<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

\Brain\Monkey\setUp();