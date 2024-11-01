<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Swifty_Menu_Welcome
 */
class Swifty_Menu_Welcome
{
    public function __construct()
    {
        add_action( 'admin_head', array( $this, 'welcome_screen_remove_menus' ) );
        add_action( 'admin_init', array( $this, 'welcome_screen_activation_redirect' ) );
        add_action( 'admin_menu', array( $this, 'welcome_screen_pages' ) );
        add_action( 'init', array( $this, 'welcome_screen_init_show' ) );
    }

    function install_wordpress_seo() {
        // now check for wordpress seo
        if( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();

        if( key_exists( 'wordpress-seo-premium/wordpress-seo-premium.php', $all_plugins ) ) {
            return false;
        }
        return true;
    }

    function admin_enqueue_scripts_styles()
    {
        wp_enqueue_script(
            'welcome_js',
            plugin_dir_url( __FILE__ ) . '../js/welcome.js',
            array( 'jquery', 'wp-util' ),
            '1.0',
            true
        );

        wp_enqueue_style(
            'welcome_css',
            plugin_dir_url( __FILE__ ) . '../css/welcome.css'
        );

        $swifty_plugins = array();
        $swifty_plugins[] = array( 'slug' => 'swifty-site-designer', 'name' => 'Swifty Site Designer' );
        $swifty_plugins[] = array( 'slug' => 'swifty-' . 'content-creator', 'name' => 'Swifty Content Creator' );
        $swifty_plugins[] = array( 'slug' => 'swifty-page-manager', 'name' => 'Swifty Page Manager' );
        $swifty_plugins[] = array( 'slug' => 'si-contact-form', 'name' => 'Fast Secure Contact Form' );
        if( $this->install_wordpress_seo() ) {
            $swifty_plugins[] = array( 'slug' => 'wordpress-seo', 'name' => 'Yoast SEO' );
        }

        wp_localize_script(
            'welcome_js',
            'ssm_wlc_data',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'ajax_nonce' => wp_create_nonce( 'swifty-site' ),
                'ajax_updates_nonce' => wp_create_nonce( 'updates' ),
                'home_url' => get_home_url(),
                'design_url' => admin_url( 'customize.php?theme=swifty-site-designer' ),
                'swifty_plugins' => $swifty_plugins,
                'i18n' => array(
                    'preparing' => __( 'Preparing', 'swifty-site' ),
                    'installing' => __( 'Installing', 'swifty-site' ),
                    'updating' => __( 'Updating', 'swifty-site' ),
                    'install_failed' => __( 'Installation of <PLUGIN> failed.' ),
                    'update_failed' => __( 'Update of <PLUGIN> failed.' ),
                    'installation_failed' => __( 'Installation failed.' ),
                )
            )
        );
    }

    // Always check for loggedin users visiting the website the first time, unless previous hosting install
    // the only access to the "swifty_ssm_skip_welcome" option is in this method.
    function welcome_screen_init_show() {

        if( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }
        if( is_user_logged_in() && ! is_admin() ) {
            $skip_welcome = get_option( 'swifty_ssm_skip_welcome', 'no' );
            if( $skip_welcome === 'yes' ) {
                return;
            }
            
            // Old AMH install?
            $install_date = get_option( 'SS2_hosting_install', -1 );
            if( mktime( 0, 0, 0, 1, 1, 2016 ) === intval( $install_date ) ) {
                // Make sure next time we are as fast as possible done here.
                update_option( 'swifty_ssm_skip_welcome', 'yes' );
                return;
            }
            update_option( 'swifty_ssm_skip_welcome', 'yes' );
            delete_transient( '_welcome_screen_activation_redirect' );
            wp_safe_redirect( add_query_arg( array( 'page' => 'swifty-welcome-screen' ), admin_url( 'index.php' ) ) );
            die();
        }
    }

    // This logic checks if the welcome dialog is needed in the admin (30 seconds after activation).
    function welcome_screen_activation_redirect()
    {
        if( ! get_transient( '_welcome_screen_activation_redirect' ) ) {
            return;
        }

        delete_transient( '_welcome_screen_activation_redirect' );

        if( is_network_admin() || isset( $_GET[ 'activate-multi' ] ) ) {
            return;
        }

        $skip_welcome = get_option( 'swifty_ssm_skip_welcome', 'no' );
        if( $skip_welcome === 'yes' ) {
            return;
        }
        
        update_option( 'swifty_ssm_skip_welcome', 'yes' );
        wp_safe_redirect( add_query_arg( array( 'page' => 'swifty-welcome-screen' ), admin_url( 'index.php' ) ) );
    }

    function welcome_screen_pages()
    {
        $my_page = add_dashboard_page(
            'Welcome to SwiftySite',
            'Welcome to SwiftySite',
            'read',
            'swifty-welcome-screen',
            array( $this, 'welcome_screen_content' )
        );

        // Load the JS and CSS conditionally.
        add_action( 'load-' . $my_page, array( $this, 'load_admin_js_css' ) );
    }

    // This function is only called when the welcome screen loads.
    function load_admin_js_css(){
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts_styles' ) );
    }

    public function welcome_screen_remove_menus()
    {
        remove_submenu_page( 'index.php', 'swifty-welcome-screen' );
    }

    function welcome_screen_content()
    {
        $skip_install_hosting = ( apply_filters( 'swifty_SS2_hosting_name', '' ) !== '' ? true : false );
        ?>
        <style>
            html.wp-toolbar { padding-top: 0; }
            #wpcontent, #wpfooter { margin-left: 0; }
            #wpcontent { padding-left: 0; }
            #wpadminbar { z-index: 0; }
            @media only screen and (max-width: 960px) {
                .auto-fold #wpcontent, .auto-fold #wpfooter {
                    margin-left: 0;
                }
            }
            @media screen and (max-width: 782px) {
                .auto-fold #wpcontent {
                    padding-left: 0;
                }
            }
        </style>

        <div class="swc_welcome_dialog">
            <div class="swc_dialog_wrapper">
                <div class="swc_dialog_window swc_panel" style="background-image: url('<?php echo plugin_dir_url( __FILE__ ); ?>../css/welcome_bg.jpg');">
                    <div class="swc_dialog_content_pane">
                        <div class="swc_dialog_content_pane_inner">
                            <div class="swc_dialog_content_pane_html">
                                <div class="swc_welcome_bottom"></div>
                                <div>
                                    <div class="swc_welcome_icon"></div>
<?php if( $skip_install_hosting ) : ?>
                                    <div class="swc_welcome_title"><?php _e( 'Zin om een mooie website te gaan maken?', 'swifty-site' ); ?></div>
                                    <div class="swc_welcome_subtitle"><?php _e( 'Welkom bij SwiftySite 3.0!', 'swifty-site' ); ?></div>
                                    <div class="swc_welcome_text">
                                        <?php _e( 'Alles is voor je in gereedheid gebracht. En niets staat je nog in de weg om snel en gemakkelijk een mooie site in elkaar te gaan zetten. Let\'s do this!', 'swifty-site' ); ?>
                                        <div class="swc_welcome_buttons">
                                            <div class="swc_button swc_btn_welcome_ready"><?php _e( 'Begin met de vormgeving van je site', 'swifty-site' ); ?><i class="fa fa-chevron-right"></i></div>
                                        </div>

                                        <div class="swc_welcome_home"><a href="javascript://"><?php _e( 'Of ga gewoon naar de homepage', 'swifty-site' ); ?></a></div>
                                    </div>
<?php endif ?>
<?php if( !$skip_install_hosting ) : ?>
                                    <div class="swc_welcome_title"><?php _e( 'Are you ready to experience', 'swifty-site' ); ?></div>
                                    <div class="swc_welcome_subtitle"><?php _e( 'the full joy of SwiftySite?', 'swifty-site' ); ?></div>
                                    <div class="swc_welcome_text">
                                        <?php _e( 'We\'re almost done setting everything up for you, but to wrap this up,<br>we need your permission to install these amazing free plugins:', 'swifty-site' ); ?>
                                        <br>
                                    </div>
                                    <div class="swc_welcome_plugins_wrapper">
                                        <div class="swc_welcome_plugins">
                                            <span><i class="fa fa-check-circle fa-lg" aria-hidden="true"></i><?php _e( 'Swifty Site Designer (Theme)', 'swifty-site' ); ?></span><br />
                                            <span><i class="fa fa-check-circle fa-lg" aria-hidden="true"></i><?php _e( 'Swifty Page Manager', 'swifty-site' ); ?></span>
<?php if( $this->install_wordpress_seo() ) : ?>
                                            <span><i class="fa fa-check-circle fa-lg" aria-hidden="true"></i><?php _e( 'Yoast WordPress Seo', 'swifty-site' ); ?></span>
<?php endif ?>
                                        </div>
                                        <div class="swc_welcome_plugins">
                                            <span><i class="fa fa-check-circle fa-lg" aria-hidden="true"></i><?php _e( 'Swifty Content Creator', 'swifty-site' ); ?></span><br />
                                            <span><i class="fa fa-check-circle fa-lg" aria-hidden="true"></i><?php _e( 'Fast Secure Contact Form', 'swifty-site' ); ?></span>
                                        </div>
                                    </div>
                                    <div class="swc_welcome_buttons">
                                        <div class="swc_button swc_btn_welcome_install"><?php _e( 'Install these required plugins & finish the setup', 'swifty-site' ); ?></div>
                                    </div>
                                    <div class="swc_welcome_text swc_welcome_status">
                                        <br>
                                    </div>
                                    <div class="swc_welcome_text swc_welcome_spinner">
                                        <i class="fa fa-3x fa-spin fa-spinner"></i>
                                    </div>
                                    <div class="swc_welcome_error">
                                        <div class="swc_welcome_error_msg"></div>
                                        <div class="swc_button swc_btn_welcome_close"><?php _e( 'Close', 'swifty-site' ); ?></div>
                                    </div>
                                    <div class="swc_welcome_note"><a href="javascript://"><?php _e( 'No, thanks. Cancel the setup please.', 'swifty-site' ); ?></a></div>
                                    <div class="swc_welcome_ready">
                                        <div class="swc_welcome_text">
                                            <?php _e( 'Installation was successful', 'swifty-site' ); ?>
                                            <br>
                                        </div>
                                        <div class="swc_welcome_buttons">
                                            <div class="swc_button swc_btn_welcome_ready"><?php _e( 'Next, let\'s do some styling', 'swifty-site' ); ?><i class="fa fa-chevron-right"></i></div>
                                        </div>
                                        
                                        <div class="swc_welcome_home"><a href="javascript://"><?php _e( 'Let\'s just go to the homepage', 'swifty-site' ); ?></a></div>
                                    </div>
<?php endif ?>
                                </div>
                                <div class="swc_welcome_laptops">
                                    <div class="swc_welcome_laptops_inner" style="background-image: url('<?php echo plugin_dir_url( __FILE__ ); ?>../css/welcome_laptops.png');"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

$swifty_menu_welcome = new Swifty_Menu_Welcome();
