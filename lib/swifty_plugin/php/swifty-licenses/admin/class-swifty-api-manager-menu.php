<?php

/**
 * Admin Menu Class
 *
 * @package Update API Manager/Admin
 * @author Todd Lahman LLC
 * @copyright   Copyright (c) Todd Lahman LLC
 * @since 1.3
 *
 */

// Exit if accessed directly
defined( 'ABSPATH' ) or exit;

require_once plugin_dir_path( __FILE__ ) . '../classes/class-swifty-ame_plugin_settings.php';

if( ! class_exists( 'SwiftyApiManagerMenu' ) ) {

    /**
     * Class SwiftyApiManagerMenu adds menu items and settings page for Swifty licenses
     */
    class SwiftyApiManagerMenu
    {
        private $plugin_name;
        private $plugin_key_name;
        private $product_id;
        private $swifty_admin_page;

        /**
         * Load admin menu
         *
         * @param $plugin_name
         * @param $plugin_key_name
         * @param $product_id
         * @param $swifty_admin_page
         */
        public function __construct( $plugin_name, $plugin_key_name, $product_id, $swifty_admin_page )
        {
            $this->plugin_name = $plugin_name;
            $this->plugin_key_name = $plugin_key_name;
            $this->product_id = $product_id;
            $this->swifty_admin_page = $swifty_admin_page;

            add_action( 'admin_init', array( $this, 'load_settings' ) );

            add_filter( 'swifty_admin_page_links_' . $this->swifty_admin_page, array( $this, 'hook_swifty_admin_page_links' ) );
        }

        /**
         * add license or deactivation tab to the options page
         *
         * @param $settings_links
         * @return mixed
         */
        function hook_swifty_admin_page_links( $settings_links )
        {
            $ame = swifty_get_ame_plugin_settings( $this->plugin_key_name );
            if( apply_filters( 'swifty_has_license_' . $this->plugin_key_name, 'D' ) === 'D' ) {
                $settings_links[ $ame->ame_activation_tab_key ] = array( 'title' => $ame->ame_menu_tab_activation_title, 'method' => array( $this, 'activation_tab_content' ), 'alternative_link' => $ame->ame_deactivation_tab_key );
            } else {
                $settings_links[ $ame->ame_deactivation_tab_key ] = array( 'title' => $ame->ame_menu_tab_deactivation_title, 'method' => array( $this, 'deactivation_tab_content' ), 'alternative_link' => $ame->ame_activation_tab_key );
            }
            return $settings_links;
        }

        /**
         * License page for activating
         */
        function activation_tab_content()
        {
            $ame = swifty_get_ame_plugin_settings( $this->plugin_key_name );
            settings_fields( $ame->ame_data_key );
            do_settings_sections( $ame->ame_activation_tab_key );
            submit_button( __( 'Activate', 'swifty-site' ) );
        }

        /**
         * License page for deactivation
         */
        function deactivation_tab_content()
        {
            $ame = swifty_get_ame_plugin_settings( $this->plugin_key_name );
            settings_fields( $ame->ame_deactivate_checkbox );
            do_settings_sections( $ame->ame_deactivation_tab_key );
            submit_button( __( 'Save Changes', 'swifty-site' ) );
        }

        /**
         * Register settings
         */
        public function load_settings()
        {
            $ame = swifty_get_ame_plugin_settings( $this->plugin_key_name );

            // Activation settings
            register_setting( $ame->ame_data_key, $ame->ame_data_key, array( $this, 'validate_options' ) );
            add_settings_section( $ame->ame_api_key, sprintf( __( 'License activation of %s', 'swifty-site' ), $this->plugin_name ), array( $this, 'wc_am_api_key_text' ), $ame->ame_activation_tab_key );
            add_settings_field( $ame->ame_activation_email, __( 'Email', 'swifty-site' ), array( $this, 'wc_am_api_email_field' ), $ame->ame_activation_tab_key, $ame->ame_api_key );
            add_settings_field( $ame->ame_api_key, __( 'License key', 'swifty-site' ), array( $this, 'wc_am_api_key_field' ), $ame->ame_activation_tab_key, $ame->ame_api_key );

            // Deactivation settings
            register_setting( $ame->ame_deactivate_checkbox, $ame->ame_deactivate_checkbox, array( $this, 'wc_am_license_key_deactivation' ) );
            add_settings_section( 'deactivate_button', sprintf( __( 'License deactivation of %s', 'swifty-site' ), $this->plugin_name ), array( $this, 'wc_am_deactivate_text' ), $ame->ame_deactivation_tab_key );
            add_settings_field( 'deactivate_button', __( 'Deactivate License Key', 'swifty-site' ), array( $this, 'wc_am_deactivate_textarea' ), $ame->ame_deactivation_tab_key, 'deactivate_button' );
        }

        /**
         * Provides text for activation section
         */
        public function wc_am_api_key_text()
        {
            echo __( "Please enter the activation details you received from us, and click the 'Activate' button.", 'swifty-site' );
        }

        /**
         * Outputs License activation code text field
         */
        public function wc_am_api_key_field()
        {
            $ame = swifty_get_ame_plugin_settings( $this->plugin_key_name );
            echo "<input id='api_key' name='" . $ame->ame_data_key . "[" . $ame->ame_api_key . "]' size='25' type='text' value='" . $ame->ame_options[ $ame->ame_api_key ] . "' />";
        }

        /**
         * Outputs License activation email text field
         */
        public function wc_am_api_email_field()
        {
            $ame = swifty_get_ame_plugin_settings( $this->plugin_key_name );
            echo "<input id='activation_email' name='" . $ame->ame_data_key . "[" . $ame->ame_activation_email . "]' size='25' type='text' value='" . $ame->ame_options[ $ame->ame_activation_email ] . "' />";
        }

        /**
         * Sanitizes and validates all input and output for Activation
         *
         * @param $input
         * @return mixed
         */
        public function validate_options( $input )
        {
            $ame = swifty_get_ame_plugin_settings( $this->plugin_key_name );

            // Load existing options, validate, and update with changes from input before returning
            $options = $ame->ame_options;

            $options[ $ame->ame_api_key ] = trim( $input[ $ame->ame_api_key ] );
            $options[ $ame->ame_activation_email ] = trim( $input[ $ame->ame_activation_email ] );

            /**
             * Plugin Activation
             */
            $api_email = trim( $input[ $ame->ame_activation_email ] );
            $api_key = trim( $input[ $ame->ame_api_key ] );

            $activation_status = get_option( $ame->ame_activated_key ) === 'Activated' ? 'Activated' : 'Deactivated';
            $valid_transient = get_transient( 'swifty_active_license_' . $this->plugin_key_name ) === 'Active';

            $checkbox_status = get_option( $ame->ame_deactivate_checkbox );

            $current_api_key = $ame->ame_options[ $ame->ame_api_key ];

            // Should match the settings_fields() value
            if( $_REQUEST[ 'option_page' ] != $ame->ame_deactivate_checkbox ) {

                if( $activation_status == 'Deactivated' || ! $valid_transient || $activation_status == '' || $api_key == '' || $api_email == '' || $checkbox_status == 'on' || $current_api_key != $api_key ) {

                    /**
                     * If this is a new key, and an existing key already exists in the database,
                     * deactivate the existing key before activating the new key.
                     */
                    if( $current_api_key != $api_key )
                        $this->replace_license_key( $current_api_key );

                    $args = array(
                        'email' => $api_email,
                        'licence_key' => $api_key,
                    );

                    $activate_results = json_decode( $ame->key()->status( $args ), true );
                    if( $activation_status == 'Activated' && ! $valid_transient ) {
                       if(  key_exists( 'status_check', $activate_results ) && ( $activate_results[ 'status_check' ] === 'inactive' ) ) {
                           $activate_results = json_decode( $ame->key()->activate( $args ), true );
                       }
                    } else {
                        $activate_results = json_decode( $ame->key()->activate( $args ), true );
                    }
                    if( (  key_exists( 'activated', $activate_results ) && ( $activate_results[ 'activated' ] === true ) ) ||
                        (  key_exists( 'status_check', $activate_results ) && ( $activate_results[ 'status_check' ] === 'active' ) ) ) {
                        add_settings_error( 'api-manager', 'activate_msg', __( 'Plugin activated. ', 'swifty-site' ) . "{$activate_results['message']}.", 'updated' );
                        update_option( $ame->ame_activated_key, 'Activated' );
                        update_option( $ame->ame_deactivate_checkbox, 'off' );
                        set_transient( 'swifty_active_license_' . $this->plugin_key_name, 'Active', DAY_IN_SECONDS * 9 );
                    }

                    $reset_activation = false;

                    if( $activate_results == false ) {
                        add_settings_error( 'api-manager', 'api_key_check_error', 'Connection failed to the License Key API server. Try again later.', 'error' );
                        $reset_activation = true;
                    }

                    if( isset( $activate_results[ 'code' ] ) ) {

                        switch( $activate_results[ 'code' ] ) {
                            case '100':
                                add_settings_error( 'api-manager', 'api_email_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
                                $reset_activation = true;
                                break;
                            case '101':
                                add_settings_error( 'api-manager', 'api_key_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
                                $reset_activation = true;
                                break;
                            case '102':
                                add_settings_error( 'api-manager', 'api_key_purchase_incomplete_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
                                $reset_activation = true;
                                break;
                            case '103':
                                add_settings_error( 'api-manager', 'api_key_exceeded_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
                                $reset_activation = true;
                                break;
                            case '104':
                                add_settings_error( 'api-manager', 'api_key_not_activated_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
                                $reset_activation = true;
                                break;
                            case '105':
                                add_settings_error( 'api-manager', 'api_key_invalid_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
                                $reset_activation = true;
                                break;
                            case '106':
                                add_settings_error( 'api-manager', 'sub_not_active_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
                                $reset_activation = true;
                                break;
                        }

                    }

                    if( $reset_activation ) {
                        $options[ $ame->ame_api_key ] = '';
                        $options[ $ame->ame_activation_email ] = '';
                        update_option( $ame->ame_activated_key, 'Deactivated' );
                        delete_transient( 'swifty_active_license_' . $this->plugin_key_name );
                    }

                } // End Plugin Activation

            }

            return $options;
        }

        /**
         * Deactivate the current license key before activating the new license key
         *
         * @param $current_api_key
         * @return bool|void
         */
        public function replace_license_key( $current_api_key )
        {
            $ame = swifty_get_ame_plugin_settings( $this->plugin_key_name );
            $args = array(
                'email' => $ame->ame_options[ $ame->ame_activation_email ],
                'licence_key' => $current_api_key,
            );

            $reset = $ame->key()->deactivate( $args ); // reset license key activation

            if( $reset == true )
                return true;

            return add_settings_error( 'api-manager', 'not_deactivated_error', __( 'The license could not be deactivated. Use the License Deactivation tab to manually deactivate the license before activating a new license.', 'swifty-site' ), 'updated' );
        }

        /**
         * Sanitizes and validates all input and output for Deactivation
         *
         * @param $input
         * @return string
         */
        public function wc_am_license_key_deactivation( $input )
        {
            $ame = swifty_get_ame_plugin_settings( $this->plugin_key_name );
            $activation_status = get_option( $ame->ame_activated_key );

            $args = array(
                'email' => $ame->ame_options[ $ame->ame_activation_email ],
                'licence_key' => $ame->ame_options[ $ame->ame_api_key ],
            );

            // For testing activation status_extra data
            // $activate_results = json_decode( AME()->key()->status( $args ), true );
            // print_r($activate_results); exit;

            $options = ( $input == 'on' ? 'on' : 'off' );

            if( $options == 'on' && ( $activation_status === 'Activated' ) && $ame->ame_options[ $ame->ame_api_key ] != '' && $ame->ame_options[ $ame->ame_activation_email ] != '' ) {

                // deactivates license key activation
                $activate_results = json_decode( $ame->key()->deactivate( $args ), true );

                // Used to display results for development
                //print_r($activate_results); exit();



                if( $activate_results[ 'deactivated' ] == true ) {
                    $update = array(
                        $ame->ame_api_key => '',
                        $ame->ame_activation_email => ''
                    );

                    $merge_options = array_merge( $ame->ame_options, $update );

                    update_option( $ame->ame_data_key, $merge_options );
                    update_option( $ame->ame_activated_key, 'Deactivated' );
                    delete_transient( 'swifty_active_license_' . $this->plugin_key_name );

                    add_settings_error( 'api-manager', 'deactivate_msg', __( 'Plugin license deactivated. ', 'swifty-site' ) . "{$activate_results['activations_remaining']}.", 'updated' );

                    return $options;
                }

                if( isset( $activate_results[ 'code' ] ) ) {

                    $reset_activation = false;

                    switch( $activate_results[ 'code' ] ) {
                        case '100':
                            add_settings_error( 'api-manager', 'api_email_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
                            $reset_activation = true;
                            break;
                        case '101':
                            add_settings_error( 'api-manager', 'api_key_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
                            $reset_activation = true;
                            break;
                        case '102':
                            add_settings_error( 'api-manager', 'api_key_purchase_incomplete_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
                            $reset_activation = true;
                            break;
                        case '103':
                            add_settings_error( 'api-manager', 'api_key_exceeded_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
                            $reset_activation = true;
                            break;
                        case '104':
                            add_settings_error( 'api-manager', 'api_key_not_activated_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
                            $reset_activation = true;
                            break;
                        case '105':
                            add_settings_error( 'api-manager', 'api_key_invalid_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
                            $reset_activation = true;
                            break;
                        case '106':
                            add_settings_error( 'api-manager', 'sub_not_active_error', "{$activate_results['error']}. {$activate_results['additional info']}", 'error' );
                            $reset_activation = true;
                            break;
                    }
                    if( $reset_activation ) {
                        $options[ $ame->ame_api_key ] = '';
                        $options[ $ame->ame_activation_email ] = '';
                        update_option( $ame->ame_activated_key, 'Deactivated' );
                        delete_transient( 'swifty_active_license_' . $this->plugin_key_name );
                    }
                }
            } else {
                return $options;
            }
        }

        /**
         * Provides text for deactivation section
         */
        public function wc_am_deactivate_text()
        {
        }

        /**
         * Outputs License deactivation checkbox field
         */
        public function wc_am_deactivate_textarea()
        {
            $ame = swifty_get_ame_plugin_settings( $this->plugin_key_name );
            echo '<input type="checkbox" id="' . $ame->ame_deactivate_checkbox . '" name="' . $ame->ame_deactivate_checkbox . '" value="on"';
            echo checked( get_option( $ame->ame_deactivate_checkbox ), 'on' );
            echo '/>';
            ?><span
            class="description"><?php _e( 'Deactivates an API License Key so it can be used on another blog.', 'swifty-site' ); ?></span>
            <?php
        }
    }
}