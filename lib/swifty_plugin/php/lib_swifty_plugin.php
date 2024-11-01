<?php
// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

if( ! class_exists( 'LibSwiftyPluginView' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'lib_swifty_plugin_view.php';
}
require_once plugin_dir_path( __FILE__ ) . 'lib/swifty_class-tgm-plugin-activation.php';
require_once plugin_dir_path( __FILE__ ) . 'swifty-licenses/swifty-license-check.php';
//require_once plugin_dir_path( __FILE__ ) . 'lib/class-swifty-theme-uninstaller.php';

/**
 * Class LibSwiftyPlugin Swifty lib functions available in edit and view mode
 */
class LibSwiftyPlugin extends LibSwiftyPluginView
{
    protected static $instance;
    protected $our_swifty_plugins = array();
    protected $added_swifty_slugs = array();
    protected $plugin_action_links = array();

    /**
     * Init singleton instance and add actions
     */
    public function __construct()
    {
        parent::__construct();

        self::$instance = $this;

        add_action( 'admin_menu', array( $this, 'hook_admin_menu_swifty_admin_licenses_page' ), 10500 );
        if( is_admin() ) {
            add_action( 'plugins_loaded', array( &$this, 'hook_plugins_loaded_update_check' ) );
        }
        add_filter( 'swifty_get_area_template_style', array( $this, 'swifty_get_area_template_style' ), 10, 3 );
        add_action( 'swifty_set_area_template_style', array( $this, 'swifty_set_area_template_style' ), 10, 3 );

        add_action( 'swifty_setup_plugin_action_links', array( $this, 'setup_plugin_action_links' ), 10, 3 );
    }

    /**
     * Static member with class instance
     *
     * @return LibSwiftyPlugin
     */
    public static function get_instance()
    {
        return self::$instance;
    }

    /**
     * Check for changed lib version
     * - add custom capabilities for locking content to the administrator
     */
    function hook_plugins_loaded_update_check() {
        global $swifty_lib_version;

        if ( get_option( 'swifty_lib_version' ) !== $swifty_lib_version ) {

            // it is no problem to run this multiple times.
            $role_object = get_role( 'administrator' );
            if( $role_object ) {
                $role_object->add_cap( 'swifty_edit_locked' );
                $role_object->add_cap( 'swifty_change_lock' );
            }

            update_option( 'swifty_lib_version', $swifty_lib_version );
        }
    }

    // SS_DOC_ARTICLE
    // id_sol: 6702
    // id_fd: 11000013962
    // id_parent_sol: 6685 // Actions and filters in Swifty Lib
    // title: Filter: swifty_get_area_template_style
    // tags: Swifty Lib,filter
    // Look for area template style.<br>
    // Returns a json string with style information for the current page / area / areatemplate<br>
    // Use the current page ID when $areaName is 'page'<br>
    // <br>
    // Example:<br>
    // <pre lang="php"><nobr>
    //   echo apply_filters( 'swifty_get_area_template_style', '{}', $areaName, $template );
    // </pre lang="php">
    // SS_DOC_END
    public function swifty_get_area_template_style( $areaStyle, $areaName, $areaTemplate ) {

        if( $areaName === 'page' ) {
            $area_id = get_the_ID();
        } else {
            $area_id = apply_filters( 'swifty_postid_from_area', -1, $areaName, $areaTemplate );
        }

        if( $area_id <> -1 ) {
            $areaStyle = get_post_meta( $area_id, 'swifty_style', true );
        }

        if( $areaStyle === '' ) {
            $areaStyle = '{}';
        }

        return $areaStyle;
    }

    // SS_DOC_ARTICLE
    // id_sol: 6705
    // id_fd: 11000013957
    // id_parent_sol: 6685 // Actions and filters in Swifty Lib
    // title: Filter: swifty_set_area_template_style
    // tags: Swifty Lib,filter
    // Set area template style.<br>
    // Store a json string with style information for the current page / area / areatemplate<br>
    // Use the $areaTemplate as ID when $areaName is 'page'<br>
    // <br>
    // Example:<br>
    // <pre lang="php"><nobr>
    //   do_action( 'swifty_set_area_template_style', $style, $areaName, $template );
    // </pre lang="php">
    // SS_DOC_END
    public function swifty_set_area_template_style( $areaStyle, $areaName, $areaTemplate ) {

        if( $areaName === 'page' ) {
            $area_id = $areaTemplate;
        } else {
            $area_id = apply_filters( 'swifty_postid_from_area', -1, $areaName, $areaTemplate );
        }

        if( $area_id <> -1 ) {
            update_post_meta( $area_id, 'swifty_style', $areaStyle );
        }
    }

    /**
     * Add a license page to the swifty admin pages. Do this only when the page name was initialized by an plugin that
     * needed this page
     */
    public function hook_admin_menu_swifty_admin_licenses_page()
    {
        global $swifty_admin_licenses_page;

        if( ! empty( $swifty_admin_licenses_page ) ) {
            LibSwiftyPlugin::get_instance()->admin_add_swifty_menu( 'Licenses', __( 'Licenses', 'swifty-site' ), $swifty_admin_licenses_page, array( $this, 'hook_swifty_admin_license_page' ), true );
        }
    }

    /**
     * Swifty page for licenses
     */
    function hook_swifty_admin_license_page()
    {
        global $swifty_admin_licenses_page;
        $this->admin_options_menu_page( $swifty_admin_licenses_page );
    }

    /**
     * Import given $url_image as attachment, return array with:
     * - url: new wordpress attachment url
     * - id: new attachment id
     * - image_url: original $url_image
     * - caption: caption of attachment - will be empty after import
     * - alt: alt of attachment - will be empty after import
     * When image already exist, return the earlier inserted attachment information. Will not
     * detect when inserted in different months only accepts png, jpg and gif files.
     *
     * @param $url_image
     * @return array
     */
    function import_attachment_from_url( $url_image ) {
        $url_image = stripslashes( $url_image );
        $urlimage = strtok( $url_image, '?' ); // keep everything before ?
        $filename = basename( $urlimage );

        $uploads = wp_upload_dir();

        $ext = pathinfo( $filename, PATHINFO_EXTENSION );
        if( ! in_array( strtolower( $ext ), array( 'png', 'jpg', 'gif' ) ) ) {
            return array(
                'url' => '',
                'id' => false,
                'image_url' => $url_image,
                'caption' => '',
                'alt' => ''
            );
        }

        $wp_filetype = wp_check_filetype( $filename, null );
        $fullpathfilename = $uploads[ 'path' ] . "/" . $filename;

        try {
            $attach_id = false;

            // is this file already available as attachment? if so then re-use it
            if( file_exists( $fullpathfilename ) ) {

                global $wpdb;

                $attachment_url = $uploads[ 'url' ] . "/" . $filename;

                // Remove the upload path base directory from the attachment URL
                $upload_dir_paths = wp_upload_dir();
                $attachment_url = str_replace( $upload_dir_paths[ 'baseurl' ] . '/', '', $attachment_url );

                // Finally, run a custom database query to get the attachment ID from the modified attachment URL
                $attach_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT wposts.ID FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta WHERE wposts.ID = wpostmeta.post_id AND wpostmeta.meta_key = '_wp_attached_file' AND wpostmeta.meta_value = '%s' AND wposts.post_type = 'attachment'",
                        $attachment_url
                    )
                );
            } else {
                // download file to server
                $image_string = swifty_read_text( $url_image );
                if( $image_string ) {
                    $upload = wp_upload_bits( $filename, null, $image_string );
                    if( !$upload || $upload['error'] ) {
                        throw new Exception( "The file cannot be saved to: " . $uploads[ 'path' ] . "/" . $filename );
                    }
                    $fullpathfilename = $upload['file'];
                    $attachment_url = $upload['url'];
                } else {
                    throw new Exception( 'Unable to fetch image: ' . $url_image );
                }
            }

            if( ! $attach_id ) {
                $attachment = array(
                    'post_mime_type' => $wp_filetype[ 'type' ],
                    'post_title' => preg_replace( '/\.[^.]+$/', '', $filename ),
                    'post_content' => '',
                    'post_status' => 'inherit',
                    'post_author' => '',
                    'post_date' => '',
                    'guid' => $attachment_url
                );
                $attach_id = wp_insert_attachment( $attachment, $fullpathfilename, 0 );
                if( ! $attach_id ) {
                    throw new Exception( "Failed to save record into database." );
                }
                require_once( ABSPATH . "wp-admin" . '/includes/image.php' );
                $attach_data = wp_generate_attachment_metadata( $attach_id, $fullpathfilename );
                wp_update_attachment_metadata( $attach_id, $attach_data );
            }

            $img = wp_prepare_attachment_for_js( $attach_id );
            $attach_caption = $img[ 'caption' ];
            $attach_alt = $img[ 'alt' ];

        } catch( Exception $e ) {
            $attach_id = false;
            $attach_caption = '';
            $attach_alt = '';
        }
        return array(
            'url' => ( $attach_id ? wp_get_attachment_url( $attach_id ) : '' ),
            'id' => $attach_id,
            'caption' => $attach_caption,
            'alt' => $attach_alt,
            'image_url' => $url_image
        );
    }

    /**
     * Add the Swifty main admin menu (once for all plugins).
     */
    public function admin_add_swifty_admin() {
        if( empty ( $GLOBALS[ 'admin_page_hooks' ][ 'swifty_admin' ] ) ) {
            apply_filters( 'admin_add_swifty_admin', null,
                'Swifty',
                'Swifty',
                'manage_options',
                'swifty_admin',
                array( &$this, 'admin_swifty_menu_page' ),
                '',
                85
            );
        }
    }

    /**
     * Add swifty menu page, when already registered return earlier registered page. Will add Swifty manin page when
     * needed
     *
     * @param $name
     * @param $swiftyname
     * @param $key
     * @param $func
     * @param $register_plugin
     * @return false|string
     */
    public function admin_add_swifty_menu( $name, $swiftyname, $key, $func, $register_plugin )
    {

        // test if it was added earlier
        if( array_key_exists( $key, $this->added_swifty_slugs ) ) {
            return $this->added_swifty_slugs[ $key ];
        }

        $this->admin_add_swifty_admin();

        // Add the admin submenu for our plugin
        $page = apply_filters( 'admin_add_swifty_menu', null, $name, $key, $func );

        if( $page ) {
            $this->added_swifty_slugs[ $key ] = $page;
        }

        if( $register_plugin ) {
            $this->our_swifty_plugins[] = array( 'key' => $key, 'name' => $name, 'swiftyname' => $swiftyname );
        }
        return $page;
    }

    /**
     * first add the menu item then replace the link in it with the url we want
     *
     * @param $name
     * @param $swiftyname
     * @param $url
     * @param $register_plugin
     */
    public function admin_add_swifty_menu_link( $name, $swiftyname, $url, $register_plugin )
    {
        $this->admin_add_swifty_menu( $name, $swiftyname, 'replace_me', null, $register_plugin );

        // we just added the menuitem, so it is always the last one...
        global $submenu;
        if( isset( $submenu[ 'swifty_admin' ] ) ) {
            $submenu[ 'swifty_admin' ][ count( $submenu[ 'swifty_admin' ] ) - 1 ][ 2 ] = $url;
        }
    }

    /**
     * The Swifty admin main menu page (For ALL Swifty plugins)
     */
    function admin_swifty_menu_page()
    {
        echo '<h1>' . __( 'Swifty Plugins', 'swifty-site' ) . '</h1>';

        echo '<h4><br>' . __( 'Active Swifty Plugins', 'swifty-site' ) . ':</h4>';

        foreach( $this->our_swifty_plugins as $plugin ) {
            echo '<a href="' . admin_url( 'admin.php?page=' . $plugin[ 'key' ] ) . '">' . $plugin[ 'name' ] . '</a><br>';
        }
    }

    /**
     * Our plugin admin menu page
     *
     * @param $admin_page
     */
    function admin_options_menu_page( $admin_page )
    {
        $settings_tabs = array();
        foreach( $this->our_swifty_plugins as $plugin ) {
            $settings_tabs[ $plugin[ 'key' ] ] = ( $this->is_ss_mode() ? $plugin[ 'swiftyname' ] : $plugin[ 'name' ] );
        }

        // make sure the selected tab exists, last active might be not added this time for some reason
        $tab = isset( $_GET[ 'page' ] ) && array_key_exists( $_GET[ 'page' ], $settings_tabs ) ? $_GET[ 'page' ] : $admin_page;

        // ask plugin for setting pages of this tab
        $settings_links = array();
        $settings_links = apply_filters( 'swifty_admin_page_links_' . $tab, $settings_links );

        reset( $settings_links );
        $first_link = key( $settings_links );

        $link = isset( $_GET[ 'link' ] ) && array_key_exists( $_GET[ 'link' ], $settings_links ) ? $_GET[ 'link' ] : null;

        // look for alternative link or use the first link available
        if( ! $link ) {
            $link = $first_link;
            foreach( $settings_links as $link_name => $link_info ) {
                if( isset( $link_info[ 'alternative_link' ] ) && isset( $_GET[ 'link' ] ) && ( $link_info[ 'alternative_link' ] === $_GET[ 'link' ] ) ) {
                    $link = $link_name;
                }
            }
        }

        ?>

        <script type="text/javascript">

            jQuery( function( $ ) {
                <?php if( $this->is_ss_mode() ) : ?>
                $( '#wpadminbar' ).hide();
                $( '#adminmenuback' ).hide();
                $( '#adminmenuwrap' ).hide();
                $( '#wpcontent' ).css( 'margin-left', '0' );
                //$('.wp-toolbar').css('padding-top', '0');
                $( '.updated' ).hide();
                $( '.error' ).hide();
                <?php endif ?>

                $( document ).on( 'click', '.swifty_back_button', function( /*ev*/ ) {
                    // in worst case fallback to main page
                    var backLocation = window.location.protocol + '//' +
                        window.location.hostname + ':' +
                        window.location.port + '/';

                    if( typeof Storage !== 'undefined' ) {
                        if( sessionStorage.back_location ) {
                            backLocation = sessionStorage.back_location;
                        }
                    }

                    window.location = backLocation;
                } );

            } );


        </script>


        <div class='wrap'>

            <?php if( $this->is_ss_mode() ) : ?>
            <div class="swifty_panel_title_container">
                <div class="swifty_panel_title_strike"></div>
                <div class="swifty_panel_title_pos">
                    <div class="swifty_title">
                        <?php endif ?>
                        <h2><?php echo __( 'Swifty Settings', 'swifty-site' ); ?></h2>
                        <?php if( $this->is_ss_mode() ) : ?>
                    </div>
                </div>
            </div>
        <br><br>
        <?php endif ?>

            <?php if( $this->is_ss_mode() ) : ?>
                <div class="swifty_back_button swifty_button">
                    <i class="fa fa-caret-left"></i>
                </div>
            <?php endif ?>

            <div class="swifty-wrapper<?php echo $this->is_ss_mode() ? ' swifty_content_right' : ''; ?>">

                <?php settings_errors( 'api-manager' ); ?>

                <h2 class="nav-tab-wrapper">
                    <?php
                    foreach( $settings_tabs as $tab_page => $tab_info ) {
                        $active_tab = $tab == $tab_page ? 'nav-tab-active' : '';
                        echo '<a class="nav-tab ' . $active_tab . '" href="?page=' . $tab_page . '">' . $tab_info . '</a>';
                    }
                    ?>
                </h2>

                <form action='options.php' method='post'>
                    <div class="main">
                        <br><br>
                        <?php
                        $active_link_method = null;

                        foreach( $settings_links as $link_name => $link_info ) {
                            $active_link = $link == $link_name ? 'nav-link-active' : '';;
                            if( $link == $link_name ) {
                                $active_link_method = $link_info[ 'method' ];
                            }
                            echo '<a class="nav-link ' . $active_link . '" href="?page=' . $tab . '&link=' . $link_name . '">' . $link_info[ 'title' ] . '</a>';
                        }
                        if( $active_link_method ) {
                            call_user_func( $active_link_method );
                        }
                        ?>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * change the permalink to postname option. Call this on plugin activation:
     * register_activation_hook( __FILE__, array( LibSwiftyPlugin::get_instance(), 'change_permalinks' ) );
     */
    public function change_permalinks()
    {
        add_action( 'permalink_structure_changed', array( &$this, 'action_permalink_structure_changed' ), 10, 2 );

        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure( '/%postname%/' );

        remove_action( 'permalink_structure_changed', array( &$this, 'action_permalink_structure_changed' ) );
    }

    /**
     * helper function: flush the rules when the permalink structure was changed
     *
     * @param $old_permalink_structure
     * @param $permalink_structure
     */
    public function action_permalink_structure_changed( $old_permalink_structure, $permalink_structure )
    {
        // make sure that the functions needed for writing htaccess are available
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/misc.php' );

        // is only triggered when something actually has changed
        global $wp_rewrite;
        $wp_rewrite->flush_rules();
    }

    /**
     * Setup filter for plugin actions links
     */
    public function setup_plugin_action_links( $slug, $link, $name ) {
        $plugin_file = "{$slug}/{$slug}.php";
        $this->plugin_action_links[ $plugin_file ] = array( 'link' => $link, 'name' => $name );
        add_filter( "plugin_action_links_{$plugin_file}", array( $this, 'add_plugin_action_links' ), 10, 2 );
    }

    /**
     * Add to links
     *
     * @param array $links
     *
     * @return array
     */
    public function add_plugin_action_links( $links, $plugin_file ) {
        if( isset( $this->plugin_action_links[ $plugin_file ] ) ) {
            $link = $this->plugin_action_links[ $plugin_file ][ 'link' ];
            $name = $this->plugin_action_links[ $plugin_file ][ 'name' ];
            array_unshift( $links, '<a href="' . $link . '" target="_blank" style="color:#ffa100;font-weight:bold;">' . $name . '</a>' );
        }
        return $links;
    }

    /**
     * create a autosave revision with this content
     *
     * @param $pid
     * @param $content
     */
    public function update_autosave_version( $pid, $content )
    {
        $post = get_post( $pid );

        // only when something has changed
        if( $post && ( normalize_whitespace( $post->post_content ) != normalize_whitespace( $content ) ) ) {
            $post->post_content = $content;
            $post = $post->to_array();
            $post[ 'post_ID' ] = $pid;
            wp_create_post_autosave( $post );
        }
    }

    /**
     * enhance wp_update_post with keeping autosave changes in swifty mode
     *
     * @param $post_id
     * @param array $postarr
     * @param bool|false $wp_error
     * @return int|WP_Error
     */
    function wp_update_post_keep_autosave( $post_id, $postarr = array(), $wp_error = false )
    {
        $autosave_content = null;

        if( $this->is_ss_mode() ) {
            $autosave_content = $this->get_autosave_version_if_newer( $post_id );
        }

        $id_saved = wp_update_post( $postarr, $wp_error );

        if( $autosave_content && ! is_wp_error( $id_saved ) ) {
            $this->update_autosave_version( $id_saved, $autosave_content );
        }

        return $id_saved;
    }
}

if( ! function_exists( 'swifty_lib_admin_enqueue_styles' ) ) {

    /**
     * Load swifty admin css and font awesome in edit mode
     */
    function swifty_lib_admin_enqueue_styles()
    {
        if( is_user_logged_in() ) {
            global $swifty_build_use;

            if( $swifty_build_use == 'build' ) {
                $swifty_css_admin_url = get_swifty_lib_dir_url( __FILE__ ) . 'css/swifty-admin.css';
            } else {
                $swifty_css_admin_url = get_swifty_lib_dir_url( __FILE__ ) . 'lib/swifty_plugin/css/swifty-admin.css';
            }

            $css_admin_version = (int) 'undefined';

            wp_enqueue_style(
                'swifty-admin.css',
                $swifty_css_admin_url,
                array(),
                $css_admin_version,
                'all'
            );

            wp_enqueue_style( 'swifty-font-awesome', '//netdna.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css' );
        }
    }

    add_action( 'admin_enqueue_scripts', 'swifty_lib_admin_enqueue_styles' );
}