<?php
// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

// remove this after new version of SCC is online, otherwise old SCC installs will fail when newer version of swiftylib is
// used in other plugins
require_once plugin_dir_path( __FILE__ ) . 'lib/swifty-captcha.php';


// SS_DOC_ARTICLE
// id_sol: 6699
// id_fd: 11000013967
// id_parent_sol: 6691 // Globals in Swifty Lib
// title: Global: $swifty_lib_dir
// tags: Swifty Lib,global
// Swifty Lib has an global to load php files only when needed.<br>
// <br>
// Some php files in the lib are only used in certain circumstances. This improves load times.<br>
// <br>
// Example:<br>
// <pre lang="php"><nobr>
// global $swifty_lib_dir;
// if( isset( $swifty_lib_dir ) ) {
//   require_once $swifty_lib_dir . '/php/lib/swifty-image-functions.php';
// }
// </pre lang="php">
// SS_DOC_END

// SS_DOC_ARTICLE
// id_sol: 6697
// id_fd: 11000013963
// id_parent_sol: 6691 // Globals in Swifty Lib
// title: Global: $swifty_lib_version
// tags: Swifty Lib,global
// Swifty Lib is versioned. Each plugin contains a version. The most recent version is used.<br>
// <br>
// Example:<br>
// <pre lang="php"><nobr>
// global $swifty_lib_version;
// </pre lang="php">
// SS_DOC_END


global $swifty_lib_version;
$swifty_lib_version = 'undefined';

if( ! function_exists( 'swifty_read_text' ) ) {

    function swifty_read_text( $filename ) {
        return call_user_func( 'file_get_contents', $filename);
    }
}

if( ! function_exists( 'swifty__' ) ) {

    function swifty__( $variable, $context ) {
        return call_user_func( '__', $variable, $context);
    }
}

/**
 * Class LibSwiftyPluginView shared functions for view mode
 */
class LibSwiftyPluginView
{
    protected static $instance_view;
    protected static $_ss_mode = null;
    protected static $_valid_modes = array( 'ss', 'wp', 'ss_force' );
    protected static $_default_mode = 'ss';
    protected static $included_view_js = false;
    protected static $included_head_script = false;

    /**
     * Constructor adds action and filter
     */
    public function __construct()
    {
        // just in case this was not set by an old autoload.php
        global $swifty_lib_dir;
        if( ! isset( $swifty_lib_dir ) ) {
            $swifty_lib_dir = dirname( plugin_dir_path( __FILE__ ) );
        }

        // test this just in case it is first created as view and later for edit
        if( ! isset( self::$instance_view ) ) {
            // allow every plugin to get to the initialization part, all plugins and theme should be loaded then
            add_action( 'after_setup_theme', array( $this, 'action_after_setup_theme' ) );
            add_filter( 'swifty_SS2_hosting_name', array( $this, 'filter_swifty_SS2_hosting_name' ) );
            add_filter( 'swifty_SS2_hosting_install_days', array( $this, 'filter_swifty_SS2_hosting_install_days' ) );
            add_filter( 'swifty_get_contentview', array( $this, 'hook_swifty_get_contentview' ), 10, 0 );

            add_filter( 'swifty_get_allow_external', array( $this, 'hook_swifty_get_allow_external' ), 10, 1 );
            add_action( 'swifty_set_allow_external', array( $this, 'hook_swifty_set_allow_external' ), 10, 1 );

            add_action( 'swifty_lib_enqueue_script_bowser', array( $this, 'enqueue_script_bowser' ) );
            add_action( 'swifty_lib_init_script_hooks', array( $this, 'init_script_hooks' ) );

            add_filter( 'swifty_lib_is_swifty_contentview', array( 'LibSwiftyPluginView', 'is_swifty_contentview' ) );
            add_filter( 'swifty_lib_is_swifty_hiddenview', array( 'LibSwiftyPluginView', 'is_swifty_hiddenview' ) );
        }
        self::$instance_view = $this;
    }

    /**
     * Singleton
     *
     * @return LibSwiftyPluginView
     */
    public static function get_instance()
    {
        return self::$instance_view;
    }

    // SS_DOC_ARTICLE
    // id_sol: 6701
    // id_fd: 11000013958
    // id_parent_sol: 6695 // Functions and methods in Swifty Lib
    // title: Function: LibSwiftyPluginView::wordpress_is_at_least_version
    // tags: Swifty Lib,function
    // Checks if WordPress is at least at version $version<br>
    // Use this when functionality is only available in certain Wordpress versions and deprecated in earlier ones.<br>
    // <br>
    // Example:<br>
    // <pre lang="php"><nobr>
    // if( ! LibSwiftyPluginView::wordpress_is_at_least_version( '4.5' ) ) {
    //   // whatever you want to do in wordpress versions before 4.5
    // }
    // </pre lang="php">
    // SS_DOC_END
    public static function wordpress_is_at_least_version( $version ) {
        global $wp_version;
        if( version_compare( $version, $wp_version, '<' ) )
            return true;
        else
            return false;
    }

    /**
     * array with active plugins
     */
    public static $required_active_plugins = array();

    // SS_DOC_ARTICLE
    // id_sol: 6700
    // id_fd: 11000013960
    // id_parent_sol: 6695 // Functions and methods in Swifty Lib
    // title: Function: LibSwiftyPluginView::is_required_plugin_active
    // tags: Swifty Lib,function
    // Is this plugin with slug $plugin_name active?<br>
    // Keep track of earlier checks to improve speed<br>
    // <br>
    // Example:<br>
    // <pre lang="php"><nobr>
    // if( LibSwiftyPluginView::is_required_plugin_active( 'si-contact-form' ) ) {
    //   // do something with this plugin
    // } else {
    //   return apply_filters( 'swifty_plugin_not_active', 'Fast Secure Contact Form' );
    // }
    // </pre lang="php">
    // SS_DOC_END
    public static function is_required_plugin_active( $plugin_name )
    {
        // do we already know the answer?
        if( array_key_exists( $plugin_name, self::$required_active_plugins ) ) {
            return self::$required_active_plugins[ $plugin_name ];
        }
        // no then we will find out: get all plugins and look for the plugin name in the directory name

        if( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $keys = array_keys( get_plugins() );

        $slug = $plugin_name;
        foreach( $keys as $key ) {
            if( preg_match( '|^' . $slug . '/|', $key ) ) {
                $slug = $key;
                break;
            }
        }
        return self::$required_active_plugins[ $plugin_name ] = is_plugin_active( $slug );
    }

    public static $required_plugin_active_swifty_site = false;
    public static $required_theme_active_swifty_site_designer = false;

    /**
     * When all plugins and themes are loaded set some static members, simplyfies testing if ssm and ssd are active
     */
    public function action_after_setup_theme()
    {
        self::$required_plugin_active_swifty_site = defined( 'SWIFTY_MENU_PLUGIN_FILE' );
        self::$required_theme_active_swifty_site_designer = defined( 'SWIFTY_SITE_DESIGNER_THEME_FILE' );
    }

    protected static $filter_swifty_SS2_hosting_name = null;

    /**
     * return the name of the SS2 hoster, when set indicates a full SS2 setup with this name as hosting partner,
     * otherwise returns $default
     *
     * @param $default
     * @return bool
     */
    public function filter_swifty_SS2_hosting_name( $default )
    {
        if( ! isset( self::$filter_swifty_SS2_hosting_name ) ) {
            self::$filter_swifty_SS2_hosting_name = get_option( 'ss2_hosting_name' );
        }
        if( self::$filter_swifty_SS2_hosting_name ) {
            return self::$filter_swifty_SS2_hosting_name;
        } else {
            return $default;
        }
    }

    /**
     * Return the days after installation on hosting server, when not set returns $default
     *
     * @param $default
     * @return bool
     */
    public function filter_swifty_SS2_hosting_install_days( $default )
    {
        $ss2_hosting_install = get_option( 'ss2_hosting_install', $default );

        if( $ss2_hosting_install !== $default ) {
            return abs( $ss2_hosting_install - time() )/60/60/24;
        }
        return $default;
    }

    // SS_DOC_ARTICLE
    // id_sol: 6698
    // id_fd: 11000013959
    // id_parent_sol: 6695 // Functions and methods in Swifty Lib
    // title: Function: LibSwiftyPluginView::add_swifty_to_admin_bar
    // tags: Swifty Lib,function
    // Add swifty menu option to the wp-admin bar.<br>
    // Look for the admin bar node 'swifty' and add it when not found.<br>
    // <br>
    // Example:<br>
    // <pre lang="php"><nobr>
    // LibSwiftyPluginView::add_swifty_to_admin_bar();
    // </pre lang="php">
    // SS_DOC_END
    public static function add_swifty_to_admin_bar()
    {

        // make sure that the font is loaded for the swifty icon:
        // wp_enqueue_style( 'font_swiftysiteui.css', $this->this_plugin_url . 'css/font_swiftysiteui.css', false, $scc_version );
        // in a hook of wp_head

        global $wp_admin_bar;

        if( ! $wp_admin_bar->get_node( 'swifty' ) ) {

            $title = '<span class="ab-icon"></span><span class="ab-label">Swifty</span>'; // Do not translate!
            $title .= '<span class="screen-reader-text">Swifty</span>'; // Do not translate!

            $wp_admin_bar->add_menu( array(
                'id' => 'swifty',
                'title' => $title,
            ) );
        }
    }

    /*
     * Is this view request in a hidden view?
     */
    public static function is_swifty_hiddenview()
    {
        return isset( $_GET[ 'swifty_hiddenview' ] );
    }

    /*
     * Is this view request in a (demo) content view?
     */
    public static function is_swifty_contentview()
    {
        return isset( $_GET[ 'swifty_contentview' ] );
    }

    /**
     * return the content that we are currently (pre)viewing
     *
     * @return bool|mixed false when 'swifty_contentview' is not set
     * 'web': use the normal page view
     * 'demo': use demo content for ssd
     */
    public function hook_swifty_get_contentview() {
        $contentview = false;
        if( isset( $_GET[ 'swifty_contentview' ] ) ) {
            $contentview = $_GET[ 'swifty_contentview' ];
        }
        if( $contentview ) {
            // make sure the input is not tampered with
            $contentview = preg_replace( '/[^0-9a-zA-Z_]/', '', $contentview );
        }
        return $contentview;
    }

    /**
     * Get the "swifty_allow_external" option, use user option if set as default value
     * 
     * @return mixed|void
     */
    public function hook_swifty_get_allow_external( $default ) {
        $value = get_user_option( 'swifty_allow_external' );
        if( $value && in_array( $value, array( 'allow', 'disallow' ), true ) ) {
            $default = $value;
        } else {
            $default = $default && ( $default !== '') ? $default : 'unknown';
        }
        return get_option( 'swifty_allow_external', $default );
    }

    /**
     * remove old user option, save option "swifty_allow_external"
     * 
     * @param $value
     */
    public function hook_swifty_set_allow_external( $value ) {
        delete_user_option( get_current_user_id(), 'swifty_allow_external' );
        update_option( 'swifty_allow_external', $value );
    }
    
    // SS_DOC_ARTICLE
    // id_sol: 6704
    // id_fd: 11000013961
    // id_parent_sol: 6695 // Functions and methods in Swifty Lib
    // title: Function: LibSwiftyPluginView::is_swifty_plugin_active
    // tags: Swifty Lib,function
    // Test if $plugin_name is active.<br>
    // All swifty plugins will respond to the 'swifty_active_plugins' filter and add it's name to the array.<br>
    // <br>
    // Example:<br>
    // <pre lang="php"><nobr>
    // if( LibSwiftyPluginView::is_swifty_plugin_active( 'swifty-site' ) ) {
    // }
    // </pre lang="php">
    // SS_DOC_END
    public static function is_swifty_plugin_active( $plugin_name )
    {
        return in_array( $plugin_name, apply_filters( 'swifty_active_plugins', array() ) );
    }

    /**
     * is swifty menu active?
     * make sure all plugins are constructed before using this function
     *
     * @return bool|null
     */
    public static function is_ss_mode()
    {
        if( ! isset( self::$_ss_mode ) ) {
            self::$_ss_mode = ( ( ( empty( $_COOKIE[ 'ss_mode' ] ) || $_COOKIE[ 'ss_mode' ] === 'ss' )
                    && self::is_swifty_plugin_active( 'swifty-site' ) )
                || self::is_ss_force() );
        }
        return self::$_ss_mode;
    }

    /**
     * set the ss_mode cookie, use ss_mode Get attribute
     * defaults to ss
     */
    public static function set_ss_mode()
    {
        // reset the ss_mode, after setting cookies the value might change
        self::$_ss_mode = null;

        $mode = '';

        if( ! empty( $_COOKIE[ 'ss_mode' ] ) && in_array( $_COOKIE[ 'ss_mode' ], self::$_valid_modes ) ) {
            $mode = $_COOKIE[ 'ss_mode' ];
        }

        if( ! empty( $_GET[ 'ss_mode' ] ) && in_array( $_GET[ 'ss_mode' ], self::$_valid_modes ) ) {
            $mode = $_GET[ 'ss_mode' ];
        }

        if( ! $mode ) {
            $mode = self::$_default_mode;
        }

        setcookie( 'ss_mode', $mode, 0, '/' );
        $_COOKIE[ 'ss_mode' ] = $mode;
    }

    /**
     * is the ss_mode cookie forced?
     *
     * @return bool
     */
    public static function is_ss_force()
    {
        return ( ! empty( $_COOKIE[ 'ss_mode' ] ) && $_COOKIE[ 'ss_mode' ] === 'ss_force' );
    }

    /**
     * improved wp_get_post_autosave, this one will use a where clause to get the autosave instead of retrieving all
     * revisions for one post
     *
     * @param $post_id
     * @param int $user_id
     * @return bool
     */
    function swifty_get_post_autosave( $post_id, $user_id = 0 ) {
        global $wpdb;

        // Construct the autosave query.
        $autosave_query = "
                SELECT *
                FROM $wpdb->posts
                WHERE post_parent = %d
                AND   post_type   = 'revision'
                AND   post_status = 'inherit'
                AND   post_name   = %s" .
            ( ( 0 !== $user_id ) ?
                "AND post_author = %d" : "" ) . "
                ORDER BY post_date DESC, ID DESC
                LIMIT 1";

        $autosave_details = $wpdb->get_results(
            $wpdb->prepare(
                $autosave_query,
                $post_id,
                $post_id . '-autosave-v1',
                $user_id
            )
        );

        if( empty( $autosave_details ) ) {
            return false;
        }

        return $autosave_details[ 0 ];
    }

    // SS_DOC_ARTICLE
    // id_sol: 6703
    // id_fd: 11000013966
    // id_parent_sol: 6695 // Functions and methods in Swifty Lib
    // title: Function: LibSwiftyPluginView::get_autosave_version_if_newer
    // tags: Swifty Lib,function
    // Find newer version of post $pid or return null if there is no newer autosave version.<br>
    // This function will get the latest autosave of any user for a post and return its content when it is newer than
    // the latest post update.<br>
    // <br>
    // Example:<br>
    // <pre lang="php"><nobr>
    // if( LibSwiftyPluginView::is_swifty_plugin_active( 'swifty-site' ) ) {
    // }
    // </pre lang="php">
    // SS_DOC_END
    public function get_autosave_version_if_newer( $pid )
    {
        // Detect if there exists an autosave newer than the post and if that autosave is different than the post
        $autosave = $this->swifty_get_post_autosave( $pid );
        $post = get_post( $pid );
        $newer_revision = null;
        if( $autosave && $post && ( mysql2date( 'U', $autosave->post_modified_gmt, false ) >= mysql2date( 'U', $post->post_modified_gmt, false ) ) ) {
            if( normalize_whitespace( $autosave->post_content ) != normalize_whitespace( $post->post_content ) ) {
                $newer_revision = $autosave->post_content;
            }
        }

        return $newer_revision;
    }

    // SS_DOC_ARTICLE
    // id_sol: 6708
    // id_fd: 11000013970
    // id_parent_sol: 6695 // Functions and methods in Swifty Lib
    // title: Function: LibSwiftyPluginView::lazy_load_js
    // tags: Swifty Lib,function
    // Use ssd enqueue_script method when ssd is the active theme, otherwise use wp_enqueue_script<br>
    // See wp_enqueue_script for description of parameters.<br>
    // <br>
    // Example:<br>
    // <pre lang="php"><nobr>
    // LibSwiftyPluginView::lazy_load_js( 'swc_lightcase_js', $lightcase_url, array( 'jquery' ) );
    // </pre lang="php">
    // SS_DOC_END
    public static function lazy_load_js( $handle, $src = false, $deps = array(), $ver = false, $in_footer = false )
    {
        if( self::$required_theme_active_swifty_site_designer ) {
            do_action( 'swifty_lazy_load_js', $handle, $src, $deps, $ver, $in_footer );
        } else {
            wp_enqueue_script( $handle, $src, $deps, $ver, $in_footer );
        }
    }

    // SS_DOC_ARTICLE
    // id_sol: 6706
    // id_fd: 11000013964
    // id_parent_sol: 6695 // Functions and methods in Swifty Lib
    // title: Function: LibSwiftyPluginView::lazy_load_js_min
    // tags: Swifty Lib,function
    // Load minified version of script on non development systems and add a bust parameter to the url.<br>
    // Use ssd enqueue_script method when ssd is the active theme, otherwise use wp_enqueue_script<br>
    // See wp_enqueue_script for description of parameters.<br>
    // <br>
    // Example:<br>
    // <pre lang="php"><nobr>
    // LibSwiftyPluginView::lazy_load_js_min( 'slider_view_js',
    //   plugin_dir_url( __FILE__ ) . 'slider_view.js', array( 'jquery', 'swc_jssor_js' ), false, true );
    // </pre lang="php">
    // SS_DOC_END
    public static function lazy_load_js_min( $handle, $src = false, $deps = array(), $ver = false, $in_footer = false )
    {
        global $swifty_build_use;
        $bust_add = '?swcv=ss2_' . '3.1.5';
        $file = $src;
        if( $swifty_build_use == 'build' ) {
            $file = preg_replace( '|\.js$|', '.min.js', $file );
        }
        $file .= $bust_add;
        self::lazy_load_js( $handle, $file, $deps, $ver, $in_footer );
    }

    // SS_DOC_ARTICLE
    // id_sol: 6707
    // id_fd: 11000013965
    // id_parent_sol: 6695 // Functions and methods in Swifty Lib
    // title: Function: LibSwiftyPluginView::lazy_register_css
    // tags: Swifty Lib,function
    // Use ssd register_style method when ssd is the active theme, otherwise use wp_register_style<br>
    // See wp_register_style for description of parameters.<br>
    // <br>
    // Example:<br>
    // <pre lang="php"><nobr>
    // global $scc_version;
    // LibSwiftyPluginView::lazy_register_css( 'swcreator_swcreator_css', $this->this_plugin_url . 'css/swcreator.css', false, $scc_version );
    // </pre lang="php">
    // SS_DOC_END
    public static function lazy_register_css( $handle, $src = false, $deps = array(), $ver = false, $media = 'all' )
    {
        if( self::$required_theme_active_swifty_site_designer ) {
            do_action( 'swifty_lazy_register_css', $handle, $src, $deps, $ver, $media );
        } else {
            wp_register_style( $handle, $src, $deps, $ver, $media );
        }
    }
    
    // SS_DOC_ARTICLE
    // id_sol: 6710
    // id_fd: 11000013969
    // id_parent_sol: 6695 // Functions and methods in Swifty Lib
    // title: Function: LibSwiftyPluginView::lazy_load_css
    // tags: Swifty Lib,function
    // Use ssd enqueue_style method when ssd is the active theme, otherwise use wp_enqueue_style<br>
    // See wp_enqueue_style for description of parameters.<br>
    // <br>
    // Example:<br>
    // <pre lang="php"><nobr>
    // LibSwiftyPluginView::lazy_load_css( 'swcreator_swcreator_css' );
    // </pre lang="php">
    // SS_DOC_END
    public static function lazy_load_css( $handle, $src = false, $deps = array(), $ver = false, $media = 'all' )
    {
        if( self::$required_theme_active_swifty_site_designer ) {
            do_action( 'swifty_lazy_load_css', $handle, $src, $deps, $ver, $media );
        } else {
            wp_enqueue_style( $handle, $src, $deps, $ver, $media );
        }
    }

    /**
     * Enqueue script for browser detection. Used in logged-in view / admin / swifty scc
     */
    public function enqueue_script_bowser() {

        add_action( 'wp_enqueue_scripts', array( $this, 'hook_enqueue_scripts_bowser' ) );
        add_action( 'swifty_enqueue_scripts', array( $this, 'hook_enqueue_scripts_bowser' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'hook_enqueue_scripts_bowser' ) );
    }

    /**
     * enqueue script for browser detection when logged-in
     */
    public function hook_enqueue_scripts_bowser() {

        if( is_user_logged_in() ) {
            global $swifty_build_use;
            $bust_add = '?swcv=ssd_' . '3.1.5';
            if( $swifty_build_use === 'build' ) {
                $script_file_url = get_swifty_lib_dir_url( __FILE__ ) . 'js/libs/bowser.min.js' . $bust_add;
            } else {
                $script_file_url = get_swifty_lib_dir_url( __FILE__ ) . 'lib/swifty_plugin/js/lib/bowser.dev.js' . $bust_add;
            }

            $script_version = (int) '1478692376000';

            wp_enqueue_script(
                'bowser_js',
                $script_file_url,
                array(),
                $script_version
            );
        }
    }

    /**
     * initialise head and footer hooks for swifty scripts
     */
    public function init_script_hooks() {
        // include initialization script in header
        add_action( 'wp_head', array( $this, 'hook_wp_head_include_head_script' ), 1 );
        // include view.js in footer
        add_action( 'wp_footer', array( $this, 'hook_wp_footer_include_view_js' ), 1 );
    }

    /**
     * wp_footer action, include the view.js loading
     */
    public function hook_wp_footer_include_view_js() {
        self::echo_included_view_js();
    }

    /**
     * echo the script that will load the view.js
     */
    public static function echo_included_view_js() {
        if(! self::$included_view_js ) {
            self::$included_view_js = true;

            global $swifty_build_use;
            $bust_add = '?swcv=ssd_' . '3.1.5';
            if( $swifty_build_use === 'build' ) {
                $view_file = get_swifty_lib_dir_url( __FILE__ ) . 'js/view.min.js' . $bust_add;
            } else {
                $view_file = get_swifty_lib_dir_url( __FILE__ ) . 'lib/swifty_plugin/js/view.js' . $bust_add;
            }
?>
<script>
    var element = document.createElement("script");
    element.src = '<?php echo$view_file; ?>';
    document.body.appendChild(element);
</script>
<?php

        }
    }

    /**
     * wp_head action, include some js script needed in the head
     */
    public function hook_wp_head_include_head_script() {
        self::echo_include_head_script();
    }

    /**
     * echo some js script needed in the head
     */
    public static function echo_include_head_script() {
        if( ! self::$included_head_script ) {
            self::$included_head_script = true;
?>
<script>
var ssd_status_onload = 0;
var ssd_list_loadCss = [];
var ssd_add_loadCss = function( s ) {
    ssd_list_loadCss.push(s);
    if( typeof swifty_do_loadCSS === 'function' ) {
        swifty_do_loadCSS();
    }
};
var ssd_list_loadJs = [];
var ssd_list_loadJs_done = false;
var ssd_add_loadJs = function( s ) {
    ssd_list_loadJs.push(s);
    if( typeof swifty_do_loadJs === 'function' ) {
        swifty_do_loadJs();
    }
};
var ssd_list_loadFont = [];
var ssd_add_loadFont = function( s ) {
    ssd_list_loadFont.push(s);
    if( typeof swifty_do_loadFont === 'function' ) {
        swifty_do_loadFont();
    }
};
var swifty_list_exec = [ { 'status': 'hold', 'for': 'page_loaded' } ];
var swifty_add_exec = function( s ) {
    swifty_list_exec.push(s);
    if( typeof swifty_do_exec === 'function' ) {
        swifty_do_exec();
    }
};
</script>
<?php
        }
    }
}

/**
 * load the swifty font, only load the latest version.
 */
if( ! function_exists( 'swifty_lib_view_enqueue_styles' ) ) {

    /**
     * load swifty font only when user is logged in. Use latest version of font
     */
    function swifty_lib_view_enqueue_styles()
    {
        if( is_user_logged_in() ) {
            global $swifty_build_use;

            if( $swifty_build_use == 'build' ) {
                $swifty_font_url = get_swifty_lib_dir_url( __FILE__ ) . 'css/swifty-font.css';
            } else {
                $swifty_font_url = get_swifty_lib_dir_url( __FILE__ ) . 'lib/swifty_plugin/css/swifty-font.css';
            }

            $font_version = (int) '1478692376000';

            wp_enqueue_style(
                'swifty-font.css',
                $swifty_font_url,
                array(),
                $font_version,
                'all'
            );
        }
    }

    // load swifty font in both view and edit
    add_action( 'wp_enqueue_scripts', 'swifty_lib_view_enqueue_styles' );
    add_action( 'admin_enqueue_scripts', 'swifty_lib_view_enqueue_styles' );
}

// SS_DOC_ARTICLE
// id_sol: 6709
// id_fd: 11000013968
// id_parent_sol: 6695 // Functions and methods in Swifty Lib
// title: Function: get_swifty_lib_dir_url
// tags: Swifty Lib,function
// Returns the plugin or theme url depending on the $file that is used.<br>
// <br>
// <br>
// Example:<br>
// <pre lang="php"><nobr>
// $swifty_css_admin_url = get_swifty_lib_dir_url( __FILE__ ) . 'css/swifty-admin.css';
// </pre lang="php">
// SS_DOC_END
if( ! function_exists( 'get_swifty_lib_dir_url' ) ) {

    /**
     * returns the plugin or theme url depending on the $file that is used
     * 
     *
     * @param $file
     * @return string
     */
    function get_swifty_lib_dir_url( $file )
    {
        // we need to work around the plugin dir link we use in our development systems
        $plugin_dir = dirname( dirname( dirname( dirname( $file ) ) ) );
        // get plugin name
        $plugin_basename = basename( $plugin_dir );

        // when the lib is used in a theme then the lib is located in the sub folder 'ssd', use this to detect that
        // the $file is used in a theme and not in a plugin
        // make sure we do not use the theme sub-folder of 'ssd' as plugin name
        if( $plugin_basename != 'ssd' ) {
            // this is a plugin
            $dir_url = trailingslashit( plugins_url( rawurlencode( $plugin_basename ) ) );
        } else {
            // this is a theme
            global $swifty_build_use;

            // get theme name
            $theme_basename = basename( dirname( $plugin_dir ) );
            $dir_url = trailingslashit( get_template_directory_uri( rawurlencode( $theme_basename ) ) );

            // on non builds we also need this 'ssd' sub folder
            if( $swifty_build_use != 'build' ) {
                $dir_url = trailingslashit( $dir_url . 'ssd' );
            }
        }
        return $dir_url;
    }
}