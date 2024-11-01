<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Swifty_Menu SSM toolbar
 */
class Swifty_Menu
{
    protected $plugin_name;
    protected $plugin_file;
    protected $plugin_dir;
    protected $plugin_url;
    protected $version;
    protected $swifty_admin_page = 'swifty_site_admin';
    protected $is_scc_active = false;
    protected $is_spm_active = false;
    protected $ssd_theme_exists = false;

    /**
     * constructor, set actions and filters
     */
    public function __construct()
    {
        global $swifty_build_use;

        $this->plugin_dir = dirname( __FILE__ );
        $this->plugin_dir_url = plugins_url( rawurlencode( basename( dirname( $this->plugin_dir ) ) ) );

        $this->plugin_name = 'swifty-site'; // use this in hook_swifty_active_plugins after changing it
        // to swifty-site
        $this->version = '3.1.5';

        // Workaround to get a valid filename while using linked folders on our dev systems
        $info = pathinfo( SWIFTY_MENU_PLUGIN_FILE );
        $this->plugin_file = basename( $info[ 'dirname' ] ) . '/' . $info[ 'basename' ];

        $this->is_spm_active = LibSwiftyPluginView::is_required_plugin_active( 'swifty-page-manager' );
        $this->is_scc_active = LibSwiftyPluginView::is_required_plugin_active( 'swifty-' . 'content-creator' );

        register_activation_hook( $this->plugin_file, array( 'Swifty_Menu', 'activate' ) );

        // Priority high, so $required_theme_active_swifty_site_designer is set.
        add_action( 'after_setup_theme', array( $this, 'action_after_setup_theme' ), 9999 );
        
        add_action( 'parse_request', array( $this, 'hook_parse_request' ) );
        add_filter( 'swifty_active_plugins', array( $this, 'hook_swifty_active_plugins' ) );
        add_filter( 'swifty_active_plugin_versions', array( $this, 'hook_swifty_active_plugin_versions' ) );
        add_filter( 'swifty_get_gui_mode_default', array( &$this, 'hook_swifty_get_gui_mode_default' ), 10, 1 );

        if( $swifty_build_use != 'build' ) {
            load_plugin_textdomain( 'swifty-site', false, 'swifty-site/languages' );
        } else {
            load_plugin_textdomain( 'swifty-site', false, dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/' );
        }

        if( ! is_admin() ) {
            do_action( 'swifty_lib_enqueue_script_bowser' );
            add_action( 'init', array( LibSwiftyPlugin::get_instance(), 'set_ss_mode' ), 1 );
            add_action( 'wp_head', array( $this, 'sm_admin_bar_render' ), 1000 );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
            add_action( 'wp_logout', array( $this, 'clean_up_ss_cookie' ), 1000 );
            add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar_in_ss_mode' ) );
        } else {
            add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        }

        add_action( 'admin_init', array( $this, 'hook_admin_init' ) );
        add_action( 'admin_menu', array( &$this, 'hook_admin_add_swifty_menu_plugins' ), 9000 );
        add_filter( 'admin_add_swifty_menu', array( &$this, 'hook_admin_add_swifty_menu' ), 1, 4 );
        add_filter( 'admin_add_swifty_admin', array( &$this, 'hook_admin_add_swifty_admin' ), 1, 8 );

        // Add the admin menu link Back to Swifty
        add_action( 'admin_menu', array( &$this, 'hook_admin_menu' ), 20000 );

        // only set these defaults on hosting sites
        if( apply_filters( 'swifty_SS2_hosting_name', false ) ) {
            add_filter( 'scc_plugin_options_default_ptag_bottom_margin', array( $this, 'hook_ptag_bottom_margin_default' ) );
            add_filter( 'scc_plugin_options_default_wpautop', array( $this, 'hook_wpautop_default' ) );
        }

        add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 900 );
        add_action( 'login_enqueue_scripts', array( $this, 'hide_wp_logo_login' ), 1 );

        // Redirect to homepage after login
        add_filter( 'login_redirect', array( $this, 'hook_login_redirect' ), 10, 3 );

        // Make sure the xml sitemap url is added to robots.txt
        add_filter( 'robots_txt', array( $this, 'hook_robots_txt' ), 10, 2 );

    }

    /**
     * Make sure the xml sitemap url is added to robots.txt
     */
    public function hook_robots_txt( $output, $public ) {
        if( strpos( $output, 'Sitemap:' ) === false ) {
            // Use our own xml sitemap url.
            $xmlUrl = sprintf( "%s/sitemap.xml", get_site_url() );

            if( defined( 'WPSEO_VERSION' ) && class_exists( 'WPSEO_Options' ) ) {
                $yoastXmlOption = WPSEO_Options::get_option( 'wpseo_xml' );
                if( $yoastXmlOption[ 'enablexmlsitemap' ] ) {
                    // Use the Yoast xml sitemap url.
                    $xmlUrl = sprintf( "%s/sitemap_index.xml", get_site_url() );
                }
            }

            return $output . 'Sitemap: ' . $xmlUrl . "\n";
        }
        return $output;
    }

    public function action_after_setup_theme() {
        $ssd_theme = wp_get_theme( 'swifty-site-designer' );

        $this->ssd_theme_exists = $ssd_theme->exists();
    }

    /**
     * for ss2 use a different default value than without swifty menu plugin
     *
     * @return string
     */
    public function hook_ptag_bottom_margin_default()
    {
        return '1';
    }

    /**
     * for ss2 use a different default value than without swifty menu plugin
     *
     * @return string
     */
    public function hook_wpautop_default()
    {
        return 'off';
    }

    /**
     * change the login form to our Swifty design
     */
    public function hide_wp_logo_login()
    {
        if( LibSwiftyPluginView::is_ss_mode() ) {
            echo '<style>h1:before { content: "' . __('Your SwiftySite Login', 'swifty-site') . '";</style>';

            wp_enqueue_style(
                'swifty-mode',
                plugin_dir_url( __FILE__ ) . '../css/swifty-mode.css',
                array(),
                $this->version,
                'all'
            );
        }
    }

    /**
     * Redirect to homepage after login
     */
    public function hook_login_redirect()
    {
        return home_url();
    }

    /**
     * Initialize Wordpress on activating:
     * - set permalink
     * - no comments by default
     * - at least 1 home page
     * - install and activate ssd theme
     */
    public static function activate()
    {
        set_transient( '_welcome_screen_activation_redirect', true, 30 );

        $on_amh_server = ( 'AMH' === apply_filters( 'swifty_SS2_hosting_name', false ) );

        if( $on_amh_server ) {
            update_option( 'permalink_structure', '/%postname%/' );
            // Allow AMH clients to change this setting on their site, we will set it in our installation script.
            //update_option( 'default_comment_status', 'closed' );
        }
        $page_ids = get_all_page_ids();
        $page_id = null;

        if( ! count( $page_ids ) ) {
            $user = wp_get_current_user();
            $page_id = wp_insert_post( array(
                    'post_title' => ucfirst( __( 'home', 'swifty-site' ) ),
                    'post_content' => ucfirst( __( 'welcome', 'swifty-site' ) ),
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_author' => $user->ID
                )
            );
        } else {
            $page_id = min( $page_ids );
        }

        if( $page_id && $on_amh_server ) {
            update_option( 'show_on_front', 'page' );
            update_option( 'page_on_front', $page_id );

            // Write .htaccess
            flush_rewrite_rules();
        }

        // only do this when using an official build
        global $swifty_build_use;
        if( $swifty_build_use == 'build' ) {
            // manually add our own plugins, otherwise they are not available in activation
            swiftylife_register_required_plugins();

            if( $on_amh_server ) {
                Swifty_TGM_Plugin_Activation::$instance->install_plugins();

                global $swifty_lib_dir;
                if( isset( $swifty_lib_dir ) ) {
                    require_once $swifty_lib_dir . '/php/lib/class-swifty-theme-installer.php';
                }

                if( class_exists( 'Swifty_Theme_Installer' ) ) {
                    $theme_installer = new Swifty_Theme_Installer( 'swiftyget:ssd', new Automatic_Upgrader_Skin() );
                    $theme_installer->check_swifty_theme();
                }
            }
        }
    }

    /**
     * redirect to wp-login.php page from login
     *
     * @param $wp
     */
    public function hook_parse_request( &$wp )
    {
        if( ! empty( $wp->request ) ) {
            if( strcasecmp( $wp->request, 'login' ) == 0 ) {
                wp_redirect( wp_login_url( home_url() ) );
                exit();
            }
        }
    }

    /**
     * Called via Swifty filter 'swifty_active_plugins'
     *
     * Add the plugin name to the array
     */
    public function hook_swifty_active_plugins( $plugins )
    {
        $plugins[] = 'swifty-site';
        return $plugins;
    }

    public function hook_swifty_active_plugin_versions( $plugins )
    {
        $plugins['swifty-site'] = array( 'version' => $this->version );
        return $plugins;
    }

    /**
     * When in swifty mode make easy the default gui mode
     *
     * @param $default
     * @return string
     */
    public function hook_swifty_get_gui_mode_default( $default ) {
        if( LibSwiftyPluginView::is_ss_mode() ) {
            return 'easy';
        } else {
            return $default;
        }
    }

    /**
     * hide wp admin bar in swifty mode when user has no edit rights
     *
     * @param $content
     * @return bool
     */
    public function hide_admin_bar_in_ss_mode( $content )
    {
        if( LibSwiftyPluginView::is_ss_mode() ) {
            if( current_user_can( 'edit_pages' ) ) {
                return false;
            } else {
                if( is_user_logged_in() ) {
                    return true;
                }
            }
        }

        return $content;
    }

    /**
     * disable ss_mode cookie
     */
    public function clean_up_ss_cookie()
    {
        if( isset( $_COOKIE[ 'ss_mode' ] ) ) {
            unset( $_COOKIE[ 'ss_mode' ] );
            setcookie( 'ss_mode', '', time() - 3600 );
        }
    }

    /**
     * add pages for managing the website externally. A admin login in WordPress must be active before those pages
     * becomes available. And only on AMH servers
     */
    public function admin_menu()
    {
        if( current_user_can( 'edit_pages' ) ) {
            $swifty_SS2_hosting_name = apply_filters( 'swifty_SS2_hosting_name', false );
            if( $swifty_SS2_hosting_name === 'AMH' ) {
                $this->_add_page( 'ss_trash_all', array( $this, 'ss_trash_all' ) );
                $this->_add_page( 'ss_after_import', array( $this, 'ss_after_import' ) );
                $this->_add_page( 'ss_switch_http', array( $this, 'ss_switch_http' ) );
                $this->_add_page( 'ss_switch_https', array( $this, 'ss_switch_https' ) );
            }
        }
    }

    /**
     * return the name of plugin as shown in the settings tabs. depends on the installation of the hosting option
     *
     * @return string
     */
    public function get_admin_page_title()
    {
        $swifty_SS2_hosting_name = apply_filters( 'swifty_SS2_hosting_name', false );
        if( $swifty_SS2_hosting_name ) {
            $admin_page_title = 'SwiftySite';
        } else {
            $admin_page_title = 'SwiftySite';
        }
        return $admin_page_title;
    }

    /**
     * add swifty admin pages
     */
    function hook_admin_add_swifty_menu_plugins()
    {
        add_filter( 'swifty_admin_page_links_' . $this->swifty_admin_page, array( $this, 'hook_swifty_admin_page_links' ) );

        LibSwiftyPlugin::get_instance()->admin_add_swifty_menu( $this->get_admin_page_title(), __('Site', 'swifty-site'), $this->swifty_admin_page, array( &$this, 'admin_ssm_menu_page' ), true );

        if ( Swifty_TGM_Plugin_Activation::get_instance()->is_admin_menu_needed() ) {
            LibSwiftyPlugin::get_instance()->admin_add_swifty_menu( __( 'Required plugins', 'swifty-site' ), __( 'Required plugins', 'swifty-site' ), 'swifty_required_plugins', array(Swifty_TGM_Plugin_Activation::get_instance(), 'install_plugins_page'), false);
        }

        if( get_option( 'ss2_hosting_name' ) !== 'AMH' ) {
            do_action( 'swifty_setup_plugin_action_links', $this->plugin_name, 'https://www.swifty.online/?rss3=wpaplgpg', __( 'More Swifty Plugins', 'swifty-site' ) );
        }
    }

    function hook_admin_menu()
    {
        // Add the admin settings menu for this plugin
        LibSwiftyPlugin::get_instance()->admin_add_swifty_menu_link(
            __( 'Switch to SwiftySite', 'swifty-site' ),
            __( 'Switch to SwiftySite', 'swifty-site' ),
            esc_url( add_query_arg( 'ss_mode', 'ss', home_url() ) ),
            false );
    }

    function hook_admin_add_swifty_menu( $page, $name, $key, $func )
    {
        if( ! $page ) {
            $page = add_submenu_page( 'swifty_admin', $name, $name, 'manage_options', $key, $func );
        }
        return $page;
    }

    function hook_admin_add_swifty_admin( $done, $v1, $v2, $v3, $v4, $v5, $v6, $v7 )
    {
        if( ! $done ) {
            add_menu_page( $v1, $v2, $v3, $v4, $v5, $v6, $v7 );
        }
        return true;
    }

    /**
     * Called via admin_menu hook
     *
     * Add links to admin menu
     */
    public function hook_swifty_admin_page_links( $settings_links )
    {
        $settings_links['general'] = array( 'title' => __( 'General', 'swifty-site' ), 'method' => array( &$this, 'ssm_tab_options_content' ) );

        return $settings_links;
    }

    // Our plugin admin menu page
    function admin_ssm_menu_page()
    {
        LibSwiftyPlugin::get_instance()->admin_options_menu_page( $this->swifty_admin_page );
    }

    function ssm_tab_options_content()
    {
        settings_fields( 'ssm_plugin_options' );
        do_settings_sections( 'ssm_plugin_options_page' );
        submit_button();
        echo '<p>' . 'SwiftySite ' . $this->version . '</p>';
    }

    /**
     * add the ssm options and settings and bind them to the correct setting section
     */
    function hook_admin_init() {
        register_setting( 'ssm_plugin_options', 'ssm_show_wp_mode' );

        add_settings_section(
            'ssm_plugin_options_main_id',
            '',
            array( $this, 'ssm_plugin_options_main_text_callback' ),
            'ssm_plugin_options_page'
        );

        add_settings_field(
            'ssm_plugin_options_ssm_show_wp_mode',
            __( 'Hide WP mode button', 'swifty-site' ),
            array( $this, 'plugin_setting_ssm_show_wp_mode' ),
            'ssm_plugin_options_page',
            'ssm_plugin_options_main_id'
        );
    }

    function ssm_plugin_options_main_text_callback()
    {
    }

    function plugin_setting_ssm_show_wp_mode() {
        $ssm_show_wp_mode = get_option( 'ssm_show_wp_mode', '' );

        echo '<input ' .
            'type="checkbox" ' .
            'id="ssm_plugin_options_ssm_show_wp_mode" ' .
            'name="ssm_show_wp_mode" ' .
            'value="hide" ' .
            checked( 'hide', $ssm_show_wp_mode, false ) .
            '/>';

        echo '<label for="ssm_plugin_options_ssm_show_wp_mode">' .
            __( 'Hide the button for switching to WP mode (for non-Admin users).', 'swifty-site' ) .
            '</label>';
    }


    public function enqueue_styles()
    {
        if( current_user_can( 'edit_pages' ) && LibSwiftyPluginView::is_ss_mode() ) {
            wp_enqueue_style(
                $this->plugin_name,
                plugin_dir_url( __FILE__ ) . '../css/swifty-site.css',
                array(),
                $this->version,
                'all'
            );
        }
    }

    public function enqueue_scripts()
    {
        if( current_user_can( 'edit_pages' ) && LibSwiftyPluginView::is_ss_mode() ) {
            wp_enqueue_script(
                $this->plugin_name,
                plugin_dir_url( __FILE__ ) . '../js/swifty-site.js',
                array( 'jquery' ),
                $this->version,
                false
            );

            $url = ( isset( $_SERVER[ 'HTTPS' ] ) ? 'https' : 'http' ) . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

            wp_localize_script(
                $this->plugin_name,
                'ssm_data',
                array(
                    'back_location' => $url,
                    'spm_location' => admin_url( 'edit.php?post_type=page&page=page-tree' ),
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'ajax_nonce' => wp_create_nonce( 'swifty-site' ),
                    'ajax_updates_nonce' => wp_create_nonce( 'updates' )
                )
            );
        }
    }

    public function admin_bar_menu( $wp_admin_bar )
    {
        if( current_user_can( 'edit_pages' ) ) {
            LibSwiftyPluginView::add_swifty_to_admin_bar();

            $title = LibSwiftyPluginView::is_ss_mode() ?
                __( 'WordPress mode', 'swifty-site' ) :
                __( 'Switch to SwiftySite', 'swifty-site' );
            $to_mode = LibSwiftyPluginView::is_ss_mode() ? 'wp' : 'ss';

            $wp_admin_bar->add_node(
                array(
                    'parent' => 'swifty',
                    'id' => 'ss-mode',
                    'title' => $title,
                    'href' => is_admin() ?
                        esc_url( add_query_arg( 'ss_mode', $to_mode, home_url() ) ) :
                        esc_url( add_query_arg( 'ss_mode', $to_mode ) )
                )
            );
        }
    }


// SS_DOC_ARTICLE
// id_sol: 6981
// id_fd: 11000022496
// id_parent_sol: 6733 // Actions and filters in Swifty Site
// title: Filter: swifty_hide_toolbar
// tags: Swifty Site,filter
// Swifty Site has an option for hiding the toolbar.<br>
// <br>
// By applying this filter you can hide the toolbar when you return true.<br>
// <br>
// Example:<br>
// <pre lang="php"><nobr>
// add_filter( 'swifty_hide_toolbar', '__return_true' );
// </pre lang="php">
// SS_DOC_END


    public function sm_admin_bar_render()
    {
        global $wp_customize;
        if ( isset( $wp_customize ) || apply_filters( 'swifty_is_editing', false ) || apply_filters( 'swifty_hide_toolbar', false ) ) {
            // Do not show Swifty main menu if inside Theme customizer iframe or below SCC
            return;
        }

        if( current_user_can( 'edit_pages' ) && LibSwiftyPluginView::is_ss_mode() ) {
            global $is_IE;

            $class = 'ssm-admin-bar';
            if( wp_is_mobile() ) {
                $class .= ' mobile';
            }

            $show_wp_mode = current_user_can( 'swifty_change_lock' ) || ( get_option( 'ssm_show_wp_mode', '' ) !== 'hide' );

            $browser_warning = __( 'It seems you are using a browser that is not fully compatible with SwiftySite.\n\n' .
            'Right now only Chrome, Firefox and Safari (version 9 or higher) work completely reliable.\n\n' .
            'Please use one of these browsers te work on your site.\n\n' .
            '(Ofcourse your visitors can view your site with any browser on any device.)', 'swifty-site' );

            $pages_url = 'edit.php?post_type=page' . ( $this->is_spm_active ? '&page=page-tree' : '' );
            $design_url = 'customize.php' . ( $this->ssd_theme_exists ? '?theme=swifty-site-designer' : '' );

            $swifty_SS2_hosting_name = apply_filters( 'swifty_SS2_hosting_name', false );
            $help_url = ( $swifty_SS2_hosting_name === 'AMH' )
                ? 'https://www.alphamegahosting.com/swiftysite/help/'
                : 'https://wordpress.org/support/plugin/swifty-site';
            ?>

            <div class="swc_iframe_gradient"></div>
            <div id="smadminbar" class="<?php echo $class; ?>" role="navigation">
                <div id="sm-admin-bar-ss-logo" class="ss-logo">
                    <div class="ab-item ab-empty-item" >
                        <span class="ab-icon"></span>
                    </div>
                </div>
                <div id="sm-admin-bar-ss-content">
                    <a class="ab-item" onclick="<?php
                        if( $this->is_scc_active ) {
                        ?>
                        if( bowser.chrome || bowser.firefox || ( bowser.safari && ( bowser.version >= 9 ) ) ) {  window.location='<?php echo esc_url( add_query_arg( 'swcreator_edit', 'main' ) ); ?>'; } else { alert('<?php echo $browser_warning ?>'); }
                        <?php } else { ?>
                            window.location='<?php echo esc_url( admin_url( 'post.php?post=' . get_the_ID() . '&action=edit' ) ); ?>';
                        <?php } ?>
                    "><?php echo __( 'Edit content', 'swifty-site' ); ?></a>
                </div>
                <div id="sm-admin-bar-ss-pages">
                    <a class="ab-item"  onclick="window.location='<?php echo esc_url( admin_url( $pages_url ) ); ?>'"><?php echo __( 'Manage pages', 'swifty-site' ); ?></a>
                </div>
                <div id="sm-admin-bar-ss-design">
                    <a class="ab-item"  onclick="window.location='<?php echo esc_url( admin_url( $design_url ) ); ?>'"><?php echo __( 'Change design', 'swifty-site' ); ?></a>
                </div>
                <div id="sm-admin-bar-ss-logout" class="ss-logout right">
                    <a class="ab-item"  onclick="window.location='<?php echo esc_url( wp_logout_url() ); ?>'" title="<?php echo __( 'Log out', 'swifty-site' ); ?>">
                        <span class="ab-icon"></span>
                    </a>
                </div>
            <?php if( $show_wp_mode ) : ?>
                <div id="sm-admin-bar-wp-logo" class="wp-logo right">
                    <a class="ab-item"  onclick="window.location='<?php echo esc_url( add_query_arg( 'ss_mode', 'wp' ) ); ?>'" title="<?php echo __( 'Switch to WordPress', 'swifty-site' ); ?>">
                        <span class="ab-icon"></span>
                    </a>
                </div>
            <?php endif; ?>
                <div id="sm-admin-bar-ss-help" class="ss-help right">
                    <a class="ab-item"  href="<?php echo esc_url( $help_url ); ?>" target="_blank" title="<?php echo __( 'Help', 'swifty-site' ); ?>">
                        <span class="ab-icon"></span>
                    </a>
                </div>

                <div id="sm-admin-bar-ss-settings" class="ss-settings right">
                    <a class="ab-item"  onclick="window.location='<?php echo admin_url( 'admin.php?page=swifty_content_creator_admin' ); ?>'" title="<?php echo __( 'Settings', 'swifty-site' ); ?>">
                        <span class="ab-icon"></span>
                    </a>
                </div>
                <?php if( is_user_logged_in() ) : ?>
                    <a class="screen-reader-shortcut"
                       href="<?php echo esc_url( wp_logout_url() ); ?>"><?php _e( 'Log out', 'swifty-site' ); ?></a>
                <?php endif; ?>
            </div>
        </div>

        <?php



        }
    }


    /**
     * @param string $name
     * @param callable $callable
     * @return string - Full admin URL, for example http://domain.ext/wp-admin/?page=NAME
     */
    protected function _add_page( $name, $callable )
    {
        $hookName = get_plugin_page_hookname( $name, '' );
        add_action( $hookName, $callable );
        global $_registered_pages;
        $_registered_pages[ $hookName ] = true;
        return admin_url( '?page=' . $name );
    }

    /**
     * Trash all pages and posts.
     * Call after login in as Admin via 'secret' url: http://domain.com/wp-admin/?page=ss_trash_all
     */
    function ss_trash_all()
    {
        if( ! current_user_can( 'edit_pages' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page. KS#56' ) );
        }

        $args = array( 'post_type' => array( 'page', 'post' ),
            'post_status' => array( 'trash', 'publish', 'inherit', 'draft', 'pending',
                'auto-draft', 'future', 'private' ), // this is more than 'any'
            'numberposts' => -1 );
        $posts = get_posts( $args );
        //$prefix = 'ss_old_';
        /** @var WP_Post $post */
        foreach( $posts as $post ) {
//            if( 0 !== strpos( $post->post_title, $prefix ) ) {
//                $post_update = array(
//                    'ID' => $post->ID,
//                    'post_title' => $prefix . $post->post_title,
//                    'post_name' => $prefix . $post->post_name
//                );
//                wp_update_post( $post_update );
//            }
//            wp_trash_post( $post->ID );
            wp_delete_post( $post->ID, true );
        }

        // remove images
        $query_images_args = array(
            'post_type' => 'attachment', 'post_mime_type' =>'image', 'post_status' => 'inherit', 'posts_per_page' => -1,
        );

        $query_images = new WP_Query( $query_images_args );
        foreach ( $query_images->posts as $image) {
            wp_delete_attachment( $image->ID, true );
        }

        if( WP_SEO_REDIRECTION_OPTIONS ) {

            echo "Remove redirects<br>\n";

            global $wpdb, $table_prefix;
            $table_name = $table_prefix . 'WP_SEO_Redirection';
            $sql = "delete from $table_name";
            $wpdb->query( $sql );
        }


        echo 'Trashed all posts, pages and images.';
    }

    /**
     * Execute fixes after importing site.xml
     * Call after login in as Admin via 'secret' url: http://domain.com/wp-admin/?page=ss_after_import
     */
    function ss_after_import()
    {

        //var_dump($_GET);

        if( isset($_GET["blogname"])) {
            $blogname = htmlspecialchars( $_GET[ "blogname" ] );
            echo '<br />blogname: ' . $blogname . '<br />';
            update_option( 'blogname', $blogname );
        }

        if( isset($_GET["blogdescription"])) {
            $blogdescription = htmlspecialchars( $_GET[ "blogdescription" ] );
            echo '<br />blogdescription: ' . $blogdescription . '<br />';
            update_option( 'blogdescription', $blogdescription );
        }

        if( isset($_GET["admin_email"])) {
            $admin_email = htmlspecialchars( $_GET[ "admin_email" ] );
            echo '<br />admin_email: ' . $admin_email . '<br />';
            update_option( 'admin_email', $admin_email );
        }

        if( isset($_GET["ptag_bottom_margin"])) {
            $ptag_bottom_margin = htmlspecialchars( $_GET[ "ptag_bottom_margin" ] );
            echo '<br />ptag_bottom_margin: ' . $ptag_bottom_margin . '<br />';

            $options = get_option( 'scc_plugin_options' );
            $options[ 'ptag_bottom_margin' ] = $ptag_bottom_margin;
            update_option( 'scc_plugin_options', $options );
        }

        $args = array( 'post_type' => 'page',
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_query' => array( array( 'key' => 'ss_import_home',
                'value' => '1' ) ) );
        $posts = get_posts( $args );
        if( ! empty( $posts ) ) {
            $post = reset( $posts );
            update_option( 'page_on_front', $post->ID );
            echo 'Set homepage to: ' . $post->post_title . '<br />';
        }

        $args = array( 'post_type' => 'page',
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_query' => array( array( 'key' => 'ss_import_blog',
                'value' => '1' ) ) );
        $posts = get_posts( $args );
        if( ! empty( $posts ) ) {
            $post = reset( $posts );
            update_option( 'page_for_posts', $post->ID );
            echo 'Set blog page to: ' . $post->post_title . '<br />';
        }

        update_option( 'show_on_front', 'page' );
        update_option( 'blog_public', 0 );

        echo '<br />[ss_after_import completed]<br />';
    }

    function switch_protocol( $option_name, $use_ssl )
    {
        $url = get_option( $option_name );
        if( $use_ssl ) {
            $url = str_replace( 'http://', 'https://', $url );
        } else {
            $url = str_replace( 'https://', 'http://', $url );
        }
        update_option( $option_name, $url );
    }

    function ss_switch_http()
    {
        $this->switch_protocol( 'home', false );
        $this->switch_protocol( 'siteurl', false );

        echo '<br />[ss_switch_http completed]<br />';
    }

    function ss_switch_https()
    {
        $this->switch_protocol( 'home', true );
        $this->switch_protocol( 'siteurl', true );

        echo '<br />[ss_switch_https completed]<br />';
    }

    /**
     * Return minified filename, if exists; otherwise original filename
     */
    protected function _find_minified( $file_name )
    {
        $file_name_min = preg_replace( '|\.js$|', '.min.js', $file_name );

        if ( file_exists( $this->plugin_dir . $file_name_min ) ) {
            $file_name = $file_name_min;
        }

        return $file_name;
    }

}

$swifty_menu = new Swifty_Menu();
