<?php
// if uninstall.php is not called by WordPress, die
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

delete_option( 'em_smtp_relay_advanced_data' );
delete_option( 'em_smtp_relay_data' );