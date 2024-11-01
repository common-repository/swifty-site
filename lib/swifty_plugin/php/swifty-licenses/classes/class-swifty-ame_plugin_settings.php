<?php

// Exit if accessed directly
defined( 'ABSPATH' ) or exit;

if( ! class_exists( 'SwiftyAmePluginSettings' ) ) {

    // Performs activations and deactivations of API License Keys
    require_once( plugin_dir_path( __FILE__ ) . 'class-swifty-api-manager-key.php' );


    /**
     * Class SwiftyAmePluginSettings all settings for a license including option key names, settings tab titles
     */
    class SwiftyAmePluginSettings
    {
        public $plugin_name;

        public $ame_upgrade_url;
        public $ame_version;
        public $ame_plugin_url;

        public $ame_software_product_id;

        public $ame_data_key;
        public $ame_api_key;
        public $ame_activation_email;
        public $ame_product_id_key;
        public $ame_instance_key;
        public $ame_deactivate_checkbox_key;
        public $ame_activated_key;

        public $ame_deactivate_checkbox;
        public $ame_activation_tab_key;
        public $ame_deactivation_tab_key;
        public $ame_menu_tab_activation_title;
        public $ame_menu_tab_deactivation_title;

        public $ame_options;
        public $ame_plugin_name;
        public $ame_product_id;
        public $ame_renew_license_url;
        public $ame_instance_id;
        public $ame_domain;
        public $ame_software_version;
        public $ame_plugin_or_theme;

        public $ame_update_version;

        public $setting_menu_page;

        public $ame_extra;

        protected $_api_manager_key_instance = null;

        public function __construct( $plugin_name )
        {
            $this->plugin_name = $plugin_name;
        }

        /**
         * API Key Class.
         *
         * @return SwiftyApiManagerKey
         */
        public function key()
        {
            if( ! isset( $_api_manager_key_instance ) ) {
                $_api_manager_key_instance = new SwiftyApiManagerKey( $this->plugin_name );
            }
            return $_api_manager_key_instance;
        }

        /**
         * Return url of this plugin
         *
         * @return string
         */
        public function plugin_url()
        {
            if( isset( $this->ame_plugin_url ) ) {
                return $this->ame_plugin_url;
            }

            return $this->ame_plugin_url = plugins_url( '/', __FILE__ );
        }

        /**
         * Returns the API License Key status from the WooCommerce API Manager on the server
         *
         * @return bool|string
         */
        public function is_license_key_status()
        {
            $license_status = $this->license_key_status();
            if( ( false !== $license_status ) && ( null !== $license_status ) ) {
                $license_status_check = isset( $license_status[ 'status_check' ] ) ? $license_status[ 'status_check' ] : null;
                return ( ! empty( $license_status_check ) && $license_status_check == 'active' ) ? 'A' : 'D';
            } else {
                return false;
            }
        }

        // Returns the API License Key status from the WooCommerce API Manager on the server
        public function license_key_status()
        {
            $args = array(
                'email' => $this->ame_options[ $this->ame_activation_email ],
                'licence_key' => $this->ame_options[ $this->ame_api_key ],
            );

            return json_decode( $this->key()->status( $args ), true );
        }


    }
}

/**
 * global to store all license settings of swifty plugins
 */
$swifty_ame_plugin_settings = array();

/**
 * get license settings of plugin
 *
 * @param $plugin_name
 * @return null
 */
function swifty_get_ame_plugin_settings( $plugin_name )
{
    global $swifty_ame_plugin_settings;
    return isset( $swifty_ame_plugin_settings[ $plugin_name ] ) ? $swifty_ame_plugin_settings[ $plugin_name ] : null;
}

/**
 * set license settings of plugin
 *
 * @param $plugin_name
 * @param $settings
 */
function swifty_set_ame_plugin_settings( $plugin_name, $settings )
{
    global $swifty_ame_plugin_settings;
    $swifty_ame_plugin_settings[ $plugin_name ] = $settings;
}