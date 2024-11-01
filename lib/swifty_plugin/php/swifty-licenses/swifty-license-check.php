<?php

// Exit if accessed directly
defined( 'ABSPATH' ) or exit;

/**
 * Class SwiftyLicenseCheck Add License settings tab and update check for this plugin
 */
class SwiftyLicenseCheck
{
    // Base URL to the remote upgrade API Manager server. If not set then the Author URI is used.
    public $our_upgrade_url = 'https://www.swifty.online/?rss3=upg1';

    // URL to renew a license. Trailing slash in the upgrade_url is required.
    public $our_renew_license_url = 'https://www.swifty.online/my-account/?rss3=upg2';

    public $our_plugin_url;
    public $swifty_admin_page = '';

    // set in constructor
    public $plugin_file;     // __FILE__ from the plugin file
    public $plugin_name;     // Swifty Content Creator Visual Pack
    public $plugin_key_name; // 'swifty_scc'
    public $product_id;      // 'SCC_ALPHA';
    public $plugin_version;  // '3.1.5';

    protected static $_instance = null;

    /**
     * Construct instance and set actions when admin
     *
     * @param $plugin_file
     * @param $plugin_name
     * @param $plugin_key_name
     * @param $product_id
     * @param $plugin_version
     * @param $swifty_admin_page
     */
    public function __construct( $plugin_file, $plugin_name, $plugin_key_name, $product_id, $plugin_version, $swifty_admin_page )
    {
        $this->plugin_file = $plugin_file;
        $this->plugin_name = $plugin_name;
        $this->plugin_key_name = $plugin_key_name;
        $this->product_id = $product_id;
        $this->plugin_version = $plugin_version;
        $this->swifty_admin_page = $swifty_admin_page;

        $this->init();
    }

    /**
     * Initialize actions when admin
     */
    public function init()
    {
        // workaround to get a valid filename while using linked folders on our dev systems
        $info = pathinfo( $this->plugin_file );
        $this->plugin_file = basename( $info[ 'dirname' ] ) . '/' . $info[ 'basename' ];

        if( is_admin() ) {
            // initialize license information
            $settings = $this->set_api_manager_plugin_settings();

            // if license is not registerd / known make sure the options table is updated
            if( ! get_option( $settings->ame_instance_key ) ) {
                $this->init_license_options();
            }

            /**
             * Displays an inactive message if the API License Key has not yet been activated
             */
            if( ! $this->has_valid_license() ) {
                add_action( 'admin_notices', array( &$this, 'prot_inactive_notice' ) );
            }

            // Check for external connection blocking
            add_action( 'admin_notices', array( $this, 'check_external_blocking' ) );

            // Checks for software updatess
            require_once( plugin_dir_path( __FILE__ ) . 'classes/class-swifty-api-manager-update-api-check.php' );

            // Admin menu with the license key and license email form
            require_once( plugin_dir_path( __FILE__ ) . 'admin/class-swifty-api-manager-menu.php' );
            new SwiftyApiManagerMenu( $this->plugin_name, $this->plugin_key_name, $this->product_id, $this->swifty_admin_page );

            $options = get_option( $settings->ame_data_key );

            /**
             * Check for software updates
             */
            if( ! empty( $options ) && $options !== false ) {

                $this->our_update_check(
                    $settings->ame_upgrade_url,
                    $settings->ame_plugin_name,
                    $settings->ame_product_id,
                    $settings->ame_options[ $settings->ame_api_key ],
                    $settings->ame_options[ $settings->ame_activation_email ],
                    $settings->ame_renew_license_url,
                    $settings->ame_instance_id,
                    $settings->ame_domain,
                    $settings->ame_software_version,
                    $settings->ame_plugin_or_theme
                );
            }
        }

        add_filter( 'swifty_has_license_' . $this->plugin_key_name, array( &$this, 'hook_swifty_has_license' ) );

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
        $settings->ame_menu_tab_activation_title = sprintf( __( 'Activate %s', 'swifty-site' ), $this->plugin_name );
        $settings->ame_menu_tab_deactivation_title = sprintf( __( 'Deactivate %s', 'swifty-site' ), $this->plugin_name );

        /**
         * Set all software update data here
         */
        $settings->ame_options = get_option( $settings->ame_data_key );
        $settings->ame_plugin_name = $this->plugin_file; // same as plugin slug. if a theme use a theme name like 'twentyeleven'
        $settings->ame_product_id = get_option( $settings->ame_product_id_key ); // Software Title
        $settings->ame_renew_license_url = $this->our_renew_license_url;
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
        $settings->ame_software_version = $this->plugin_version; // The software version
        $settings->ame_plugin_or_theme = 'plugin'; // 'theme' or 'plugin'

        $settings->ame_upgrade_url = $this->our_upgrade_url;
        $settings->ame_version = $this->plugin_version;
        $settings->ame_plugin_url = $this->our_plugin_url;

        $settings->setting_menu_page = $this->swifty_admin_page;

        swifty_set_ame_plugin_settings( $this->plugin_key_name, $settings );

        return $settings;
    }

    /**
     * check for valid license in the options table.
     *
     * @return bool
     */
    function has_valid_license()
    {
        $licence_code = get_option( $this->plugin_key_name . '_activated' );
        if ( $licence_code === 'Activated' ) {
            if ( get_transient( 'swifty_active_license_' . $this->plugin_key_name ) ) {
                return true;
            } else {
                if ( get_transient( 'swifty_update_checked_' . $this->plugin_key_name ) ) {
                    return false;
                } else {
                    require_once( plugin_dir_path( __FILE__ ) . 'classes/class-swifty-ame_plugin_settings.php' );

                    $settings = swifty_get_ame_plugin_settings( $this->plugin_key_name );
                    if( ! $settings ) {
                        $settings = $this->set_api_manager_plugin_settings();
                    }
                    $license_info = $settings->is_license_key_status();
                    if( $license_info !== false ) {
                        set_transient( 'swifty_update_checked_' . $this->plugin_key_name, 'done', DAY_IN_SECONDS * 7 );
                    }
                    if( $license_info === 'A' ) {
                        set_transient( 'swifty_active_license_' . $this->plugin_key_name, 'Active', DAY_IN_SECONDS * 9 );
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Update Check Class.
     *
     * @return SwiftyApiManagerUpdateApiCheck
     */
    public function our_update_check( $upgrade_url, $plugin_name, $product_id, $api_key, $activation_email, $renew_license_url, $instance, $domain, $software_version, $plugin_or_theme, $extra = '' )
    {
        return new SwiftyApiManagerUpdateApiCheck( $upgrade_url, $plugin_name, $product_id, $api_key, $activation_email, $renew_license_url, $instance, $domain, $software_version, $plugin_or_theme, $extra );
    }

    /**
     * used for options tabs in SwiftyApiManagerMenu
     *
     * @return string
     */
    function hook_swifty_has_license()
    {
        // no license was found: return 'D' otherwise 'A'
        return $this->has_valid_license() ? 'A' : 'D';
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
     * Displays an inactive notice when the software is inactive. page=swifty_content_creator_admin&tab=
     */
    public function prot_inactive_notice()
    {
        if( ! current_user_can( 'manage_options' ) ) return;
        if( isset( $_GET[ 'page' ] ) && ( $this->swifty_admin_page === $_GET[ 'page' ] ) ) return;

        $settings = swifty_get_ame_plugin_settings( $this->plugin_key_name );
        ?>
        <div id="message" class="error">
            <p><?php printf( __( 'The license key for <b>%s</b> has not been activated, so the plugin is inactive! %sClick here%s to activate the license key and the plugin.', 'swifty-site' ), $this->plugin_name, '<a href="' . esc_url( admin_url( 'admin.php?page=' . $settings->setting_menu_page . '&link=' . $this->plugin_key_name . '_dashboard' ) ) . '">', '</a>' ); ?></p>
        </div>
        <?php
    }

    /**
     * Check for external blocking contstant
     * @return string
     */
    public function check_external_blocking()
    {
        // show notice if external requests are blocked through the WP_HTTP_BLOCK_EXTERNAL constant
        if( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL === true ) {

            // check if our API endpoint is in the allowed hosts
            $host = parse_url( $this->our_upgrade_url, PHP_URL_HOST );

            if( ! defined( 'WP_ACCESSIBLE_HOSTS' ) || stristr( WP_ACCESSIBLE_HOSTS, $host ) === false ) {
                ?>
                <div class="error">
                    <p><?php printf( __( '<b>Warning!</b> You\'re blocking external requests which means you won\'t be able to get %s updates. Please add %s to %s.', 'swifty-site' ), $this->ame_software_product_id, '<strong>' . $host . '</strong>', '<code>WP_ACCESSIBLE_HOSTS</code>' ); ?></p>
                </div>
                <?php
            }
        }
    }
}
