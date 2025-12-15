<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

use Emercury\Smtp\Core\DatabaseManager;

delete_option( 'em_smtp_relay_advanced_data' );
delete_option( 'em_smtp_relay_data' );
delete_option( 'em_smtp_email_logs' );
delete_option( 'em_smtp_error_logs' );

wp_clear_scheduled_hook('em_smtp_cleanup_logs');

require_once plugin_dir_path(__FILE__) . 'src/Core/DatabaseManager.php';
$dbManager = new DatabaseManager();
$dbManager->dropTables();