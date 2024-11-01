<?php

/**
 * WooCommerce API Manager API Key Class
 *
 * @package Update API Manager/Key Handler
 * @author Todd Lahman LLC
 * @copyright   Copyright (c) Todd Lahman LLC
 * @since 1.3
 *
 */

// Exit if accessed directly
defined( 'ABSPATH' ) or exit;

require_once plugin_dir_path( __FILE__ ) . 'class-swifty-ame_plugin_settings.php';

if( ! class_exists( 'SwiftyApiManagerKey' ) ) {

    /**
     * Access to license api endpoint on server
     * Class is instantiated as an object by other classes on-demand
     *
     * Class SwiftyApiManagerKey
     */
    class SwiftyApiManagerKey
    {

        private $plugin_name;

        public function __construct( $plugin_name )
        {
            $this->plugin_name = $plugin_name;
        }

        /**
         * API Key URL
         *
         * @param $args
         * @return string
         */
        public function create_software_api_url( $args )
        {
            $ame = swifty_get_ame_plugin_settings( $this->plugin_name );

            $api_url = add_query_arg( 'wc-api', 'am-software-api', $ame->ame_upgrade_url );

            return $api_url . '&' . http_build_query( $args );
        }

        /**
         * activate license on server
         *
         * @param $args
         * @return bool|string
         */
        public function activate( $args )
        {
            $ame = swifty_get_ame_plugin_settings( $this->plugin_name );

            $defaults = array(
                'request' => 'activation',
                'product_id' => $ame->ame_product_id,
                'instance' => $ame->ame_instance_id,
                'platform' => $ame->ame_domain,
                'software_version' => $ame->ame_software_version
            );

            $args = wp_parse_args( $defaults, $args );

            $target_url = esc_url_raw( self::create_software_api_url( $args ) );

            $request = wp_safe_remote_get( $target_url );

            if( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
                // Request failed
                return false;
            }

            $response = wp_remote_retrieve_body( $request );

            return $response;
        }

        /**
         * deactivate license on server
         *
         * @param $args
         * @return bool|string
         */
        public function deactivate( $args )
        {
            $ame = swifty_get_ame_plugin_settings( $this->plugin_name );
            $defaults = array(
                'request' => 'deactivation',
                'product_id' => $ame->ame_product_id,
                'instance' => $ame->ame_instance_id,
                'platform' => $ame->ame_domain
            );

            $args = wp_parse_args( $defaults, $args );

            $target_url = esc_url_raw( self::create_software_api_url( $args ) );

            $request = wp_safe_remote_get( $target_url );

            if( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
                // Request failed
                return false;
            }

            $response = wp_remote_retrieve_body( $request );

            return $response;
        }

        /**
         * Checks if the software is activated or deactivated
         * @param  array $args
         * @return array
         */
        public function status( $args )
        {
            $ame = swifty_get_ame_plugin_settings( $this->plugin_name );
            $defaults = array(
                'request' => 'status',
                'product_id' => $ame->ame_product_id,
                'instance' => $ame->ame_instance_id,
                'platform' => $ame->ame_domain
            );

            $args = wp_parse_args( $defaults, $args );

            $target_url = esc_url_raw( self::create_software_api_url( $args ) );

            $request = wp_safe_remote_get( $target_url );

            if( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
                // Request failed
                return false;
            }

            $response = wp_remote_retrieve_body( $request );

            return $response;
        }
    }
}