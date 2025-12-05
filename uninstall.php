<?php
// if uninstall.php is not called by WordPress, die
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

delete_option( 'em_smtp_relay_advanced_data' );
delete_option( 'em_smtp_relay_data' );
delete_option( 'em_smtp_email_logs' );
delete_option( 'em_smtp_error_logs' );

wp_clear_scheduled_hook('em_smtp_cleanup_logs');