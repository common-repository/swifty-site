<?php

// Exit if accessed directly
defined( 'ABSPATH' ) or exit;

/**
 * Class SwiftyPackInstall Add pack installation functionality.
 */
class SwiftyPackInstall
{
    // Base URL to the remote upgrade API Manager server. If not set then the Author URI is used.
    public $our_upgrade_url = 'https://www.swifty.online/?rss3=upg1';
    
    public $plugin_key_name; // plugin slug
    public $product_id;      // plugin product id in swifty shop

    /**
     * SwiftyPackInstall constructor.
     * @param $plugin_key_name
     * @param $product_id
     */
    public function __construct( $plugin_key_name, $product_id ) {
        $this->plugin_key_name = $plugin_key_name;
        $this->product_id = $product_id;
        
        $this->init();
    }

    /**
     * Init api manager and license options
     */
    public function init()
    {
        // initialize license information
        $settings = $this->set_api_manager_plugin_settings();

        // if license is not registerd / known make sure the options table is updated
        if( ! get_option( $settings->ame_instance_key ) ) {
            $this->init_license_options();
        }
    }


    /**
     * Construct settings object for plugin and add it to global settings array
     *
     * @return SwiftyAmePluginSettings
     */
    function set_api_manager_plugin_settings()
    {
        require_once( plugin_dir_path( __FILE__ ) . 'classes/class-swifty-ame_plugin_settings.php' );

        $settings = new SwiftyAmePluginSettings( $this->plugin_key_name );

        /**
         * Software Product ID is the product title string
         * This value must be unique, and it must match the API tab for the product in WooCommerce
         */
        $settings->ame_software_product_id = $this->product_id;

        /**
         * Set all data defaults here
         */
        $settings->ame_data_key = $this->plugin_key_name . '_data';
        $settings->ame_api_key = $this->plugin_key_name . '_key';
        $settings->ame_activation_email = $this->plugin_key_name . '_activation_email';
        $settings->ame_product_id_key = $this->plugin_key_name . '_product_id';
        $settings->ame_instance_key = $this->plugin_key_name . '_instance';
        $settings->ame_deactivate_checkbox_key = $this->plugin_key_name . '_deactivate_checkbox';
        $settings->ame_activated_key = $this->plugin_key_name . '_activated';

        /**
         * Set all admin menu data
         */
        $settings->ame_deactivate_checkbox = $this->plugin_key_name . '_deactivate_checkbox';
        $settings->ame_activation_tab_key = $this->plugin_key_name . '_dashboard';
        $settings->ame_deactivation_tab_key = $this->plugin_key_name . '_deactivation';

        /**
         * Set all software update data here
         */
        $settings->ame_options = get_option( $settings->ame_data_key );
        $settings->ame_plugin_name = $this->plugin_key_name; // same as plugin slug. if a theme use a theme name like 'twentyeleven'
        $settings->ame_product_id = get_option( $settings->ame_product_id_key ); // Software Title
        $settings->ame_instance_id = get_option( $settings->ame_instance_key ); // Instance ID (unique to each blog activation)
        /**
         * Some web hosts have security policies that block the : (colon) and // (slashes) in http://,
         * so only the host portion of the URL can be sent. For example the host portion might be
         * www.example.com or example.com. http://www.example.com includes the scheme http,
         * and the host www.example.com.
         * Sending only the host also eliminates issues when a client site changes from http to https,
         * but their activation still uses the original scheme.
         * To send only the host, use a line like the one below:
         *
         * $this->ame_domain = str_ireplace( array( 'http://', 'https://' ), '', home_url() ); // blog domain name
         */
        $settings->ame_domain = str_ireplace( array( 'http://', 'https://' ), '', home_url() ); // blog domain name
        $settings->ame_plugin_or_theme = 'plugin'; // 'theme' or 'plugin'
        $settings->ame_upgrade_url = $this->our_upgrade_url;

        swifty_set_ame_plugin_settings( $this->plugin_key_name, $settings );

        return $settings;
    }

    /**
     * Generate the default data arrays
     */
    public function init_license_options()
    {
        $settings = swifty_get_ame_plugin_settings( $this->plugin_key_name );
        if( ! $settings ) {
            $settings = $this->set_api_manager_plugin_settings();
        }

        $global_options = array(
            $settings->ame_api_key => '',
            $settings->ame_activation_email => '',
        );

        update_option( $settings->ame_data_key, $global_options );

        require_once( plugin_dir_path( __FILE__ ) . 'classes/class-swifty-api-manager-passwords-management.php' );

        $api_manager_password_management = new SwiftyApiManagerPasswordManagement();

        // Generate a unique installation $instance id
        $instance = $api_manager_password_management->generate_password( 12, false );

        $single_options = array(
            $settings->ame_product_id_key => $settings->ame_software_product_id,
            $settings->ame_instance_key => $instance,
            $settings->ame_deactivate_checkbox_key => 'on',
            $settings->ame_activated_key => 'Deactivated',
        );

        foreach( $single_options as $key => $value ) {
            update_option( $key, $value );
        }
    }

    /**
     * Get current license status from server
     * 
     * @param $email
     * @param $code
     * @return array|mixed|object
     */
    public function license_key_status( $email, $code )
    {
        $settings = swifty_get_ame_plugin_settings( $this->plugin_key_name );
        if( ! $settings ) {
            $settings = $this->set_api_manager_plugin_settings();
        }
        
        $args = array(
            'email' => $email,
            'licence_key' => $code,
        );

        $result = json_decode( $settings->key()->status( $args ), true );
        
        return $result;
    }

    /**
     * Activate license on server
     * 
     * @param $api_email
     * @param $api_key
     * @param $only_register    true when license was already registered and activation is not needed
     * @return bool
     */
    public function license_key_activate( $api_email, $api_key, $only_register ) {
        $settings = swifty_get_ame_plugin_settings( $this->plugin_key_name );
        if( ! $settings ) {
            $settings = $this->set_api_manager_plugin_settings();
        }

        /**
         * Plugin Activation
         */
        $args = array(
            'email' => $api_email,
            'licence_key' => $api_key,
        );

        $additional_info = '';
        if( ! $only_register ) {
            $activate_results = json_decode( $settings->key()->activate( $args ), true );
            if( is_array( $activate_results ) && key_exists( 'additional info', $activate_results ) ) {
                $additional_info = $activate_results[ 'additional info' ];
            }
        }
        if( $only_register ||
            (  key_exists( 'activated', $activate_results ) && ( $activate_results[ 'activated' ] === true ) ) ||
            (  key_exists( 'status_check', $activate_results ) && ( $activate_results[ 'status_check' ] === 'active' ) ) ) {
            update_option( $settings->ame_activated_key, $api_key );
            update_option( $settings->ame_activation_email, $api_email );
            
            update_option( $settings->ame_activated_key, 'Activated' );
            update_option( $settings->ame_deactivate_checkbox, 'off' );
            set_transient( 'swifty_active_license_' . $this->plugin_key_name, 'Active', DAY_IN_SECONDS * 9 );

            return array( true, $additional_info );
        }
        return array( false, $additional_info );
    }

    /**
     * Retrieve package source from server
     * 
     * @param $email
     * @param $code
     * @return array
     */
    public function get_download_url( $email, $code ) {

        $settings = swifty_get_ame_plugin_settings( $this->plugin_key_name );
        if( ! $settings ) {
            $settings = $this->set_api_manager_plugin_settings();
        }
        
        $args = array(
            'request' => 'pluginupdatecheck',
            'slug' => $this->plugin_key_name,
            'plugin_name' => $this->plugin_key_name,
            'product_id' => $this->product_id,
            'api_key' => $code,
            'activation_email' => $email,
            'instance' => $settings->ame_instance_id,
            'domain' => $settings->ame_domain,
            'software_version' => '',
            'extra' => '',
        );

        // Check for a plugin update
        $response = $this->plugin_information( $args );

        // Set version variables
        if( isset( $response ) && is_object( $response ) && $response !== false ) {

            if( isset ( $response->errors ) ) {
                return array( 'error', '', implode( ',', $response->errors ) );
            } else {
                return array( 'ok', $response->package, '' );
            }
        }
        return array( 'error', '', 'no response' );
    }

    /**
     * Return url for upgrading.
     * 
     * @param $args
     * @return string
     */
    private function create_upgrade_api_url( $args )
    {
        $upgrade_url = add_query_arg( 'wc-api', 'upgrade-api', $this->our_upgrade_url );

        return $upgrade_url . '&' . http_build_query( $args );
    }

    /**
     * @param $args
     * @return bool|mixed
     */
    public function plugin_information( $args )
    {
        $target_url = esc_url_raw( $this->create_upgrade_api_url( $args ) );

        $request = wp_safe_remote_get( $target_url );

        if( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $request );
        if( $body && is_serialized( $body ) ) {
            $response = unserialize( $body );
            if( is_object( $response ) ) {
                return $response;
            }
        }
        return false;
    }
}