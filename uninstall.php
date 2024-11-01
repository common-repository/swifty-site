<?php
/**
 * Plugin Uninstall Procedure
 */

//exit();
//
//// Make sure that we are uninstalling
//if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
//    exit();
//
//// Option to delete, names can contain % as wildcard
//$option_names = array( 'ssm_show_wp_mode', 'swifty_ssm_skip_welcome' );
//
//// Site options to delete
//$site_option_names = array();
//
//
//// start removing options
//
//foreach( $site_option_names as $site_option_name ) {
//    delete_site_option( $site_option_name );
//}
//
//global $wpdb;
//
//if ( !is_multisite() ) {
//    foreach( $option_names as $option_name ) {
//        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '{$option_name}'" );
//    }
//} else {
//    $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
//
//    foreach( $blog_ids as $blog_id ) {
//        switch_to_blog( $blog_id );
//
//        foreach( $option_names as $option_name ) {
//            $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '{$option_name}'" );
//        }
//    }
//
//    restore_current_blog();
//}