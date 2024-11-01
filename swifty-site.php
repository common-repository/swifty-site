<?php
/*
Plugin Name: Swifty Site
Description: Simplified menu bar for WordPress
Author: SwiftyOnline
Version: 3.1.5
Author URI: https://swifty.online/plugins/
Plugin URI: https://swifty.online/swiftysite/
*/
if ( ! defined( 'ABSPATH' ) ) exit;

global $swifty_build_use;
$swifty_build_use = 'build';

// When this constant is defined then the plugin is active
if( ! defined( 'SWIFTY_MENU_PLUGIN_FILE' ) ) {
    define( 'SWIFTY_MENU_PLUGIN_FILE', __FILE__ );
}

require_once plugin_dir_path( __FILE__ ) . 'lib/swifty_plugin/php/autoload.php';
if( is_null( LibSwiftyPlugin::get_instance() ) ) {
    new LibSwiftyPlugin();
}

if( isset( $_GET[ 'swifty_mode' ] ) ) {
    $swifty_mode = $_GET[ 'swifty_mode' ];
    if( in_array( $swifty_mode, array( 'view' ) ) ) {
        switch( $swifty_mode ) {
            case 'view':
                add_filter( 'show_admin_bar', '__return_false' );
                add_filter( 'swifty_hide_toolbar', '__return_true' );

                break;
        }
    }
}


// Welcome screen
require_once plugin_dir_path( __FILE__ ) . 'includes/class-swifty-site-welcome.php';

// The core plugin class.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-swifty-site.php';

// XML sitemap
require_once plugin_dir_path( __FILE__ ) . 'includes/class-xml-sitemap.php';

// install needed plugins
require_once plugin_dir_path( __FILE__ ) . 'includes/register-required-plugins.php';

// ajax calls
require_once plugin_dir_path( __FILE__ ) . 'includes/class-swifty-site-ajax.php';