<?php

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the required plugins for this plugin.
 *
 * The variable passed to stgmpa_register_plugins() should be an array of plugin
 * arrays.
 *
 * This function is hooked into stgmpa_init, which is fired within the
 * Swifty_TGM_Plugin_Activation class constructor.
 */
function swiftylife_register_required_plugins()
{

    $force_activation = ( 'AMH' === apply_filters( 'swifty_SS2_hosting_name', false ) );

    /**
     * Array of plugin arrays. Required keys are name and slug.
     * If the source is NOT from the .org repo, then source is also required.
     */
    $plugins = array(

        array(
            'name'      => 'Fast Secure Contact Form',
            'slug'      => 'si-contact-form',
            'version'   => '4.0.41',
            'required'  => true,
            'force_activation'   => $force_activation,
        ),
        array(
            'name'      => 'Yoast SEO',
            'slug'      => 'wordpress-seo',
            'slug-alt'  => 'wordpress-seo-premium',
            'version'   => '3.6.1',
            'required'  => true,
            'force_activation'   => $force_activation,
        ),
        array(
            'name'      => 'Swifty Page Manager',
            'slug'      => 'swifty-page-manager',
            'version'   => '1.5.5',
            'required'  => true,
            'force_activation'   => $force_activation, // If true, plugin is activated upon theme activation and cannot be deactivated until theme switch.
        ),
        array(
            'name'      => 'Swifty Content Creator',
            'slug'      => 'swifty-' . 'content-creator',
            'version'   => '1.5.2',
            'required'  => true,
            'force_activation'   => $force_activation, // If true, plugin is activated upon theme activation and cannot be deactivated until theme switch.
        ),
    );

    /**
     * Array of configuration settings. Amend each line as needed.
     * If you want the default strings to be available under your own theme domain,
     * leave the strings uncommented.
     * Some of the strings are added into a sprintf, so see the comments at the
     * end of each line for what each argument will be.
     */
    // These strings are specific for use in Swifty
    $config = array(
        'default_path' => '',                      // Default absolute path to pre-packaged plugins.
        'menu'         => 'swifty_required_plugins', // Menu slug.
        'menu_url'     => network_admin_url( 'admin.php' ),
        'has_notices'  => true,                    // Show admin notices or not.
        'dismissable'  => true,                    // If false, a user cannot dismiss the nag message.
        'dismiss_msg'  => '',                      // If 'dismissable' is false, this message will be output at top of nag.
        'is_automatic' => false,                   // Automatically activate plugins after installation or not.
        'message'      => '',                      // Message to output right before the plugins table.
        'skip_notices_on_pages' => array( 'swifty_page_swifty_content_creator_admin' ),
        'strings'      => array(
            'page_title'                      => __( 'Install Required Plugins', 'swifty-site' ),
            'menu_title'                      => __( 'Install Plugins', 'swifty-site' ),
            'installing'                      => __( 'Installing Plugin: %s', 'swifty-site' ), // %s = plugin name.
            'oops'                            => __( 'Something went wrong with the plugin API.', 'swifty-site' ),
            'notice_can_install_required'     => _n_noop( 'Swifty plugin requires the following plugin: %1$s.', 'Swifty plugin requires the following plugins: %1$s.', 'swifty-site' ), // %1$s = plugin name(s).
            'notice_can_install_recommended'  => _n_noop( 'Swifty plugin recommends the following plugin: %1$s.', 'Swifty plugin recommends the following plugins: %1$s.', 'swifty-site' ), // %1$s = plugin name(s).
            'notice_cannot_install'           => _n_noop( 'Sorry, but you do not have the correct permissions to install the %s plugin. Contact the administrator of this site for help on getting the plugin installed.', 'Sorry, but you do not have the correct permissions to install the %s plugins. Contact the administrator of this site for help on getting the plugins installed.', 'swifty-site' ), // %1$s = plugin name(s).
            'notice_can_activate_required'    => _n_noop( 'The following required plugin is currently inactive: %1$s.', 'The following required plugins are currently inactive: %1$s.', 'swifty-site' ), // %1$s = plugin name(s).
            'notice_can_activate_recommended' => _n_noop( 'The following recommended plugin is currently inactive: %1$s.', 'The following recommended plugins are currently inactive: %1$s.', 'swifty-site' ), // %1$s = plugin name(s).
            'notice_cannot_activate'          => _n_noop( 'Sorry, but you do not have the correct permissions to activate the %s plugin. Contact the administrator of this site for help on getting the plugin activated.', 'Sorry, but you do not have the correct permissions to activate the %s plugins. Contact the administrator of this site for help on getting the plugins activated.', 'swifty-site' ), // %1$s = plugin name(s).
            'notice_ask_to_update'            => _n_noop( 'The following plugin needs to be updated to its latest version to ensure maximum compatibility with Swifty: %1$s.', 'The following plugins need to be updated to their latest version to ensure maximum compatibility with Swifty: %1$s.', 'swifty-site' ), // %1$s = plugin name(s).
            'notice_cannot_update'            => _n_noop( 'Sorry, but you do not have the correct permissions to update the %s plugin. Contact the administrator of this site for help on getting the plugin updated.', 'Sorry, but you do not have the correct permissions to update the %s plugins. Contact the administrator of this site for help on getting the plugins updated.', 'swifty-site' ), // %1$s = plugin name(s).
            'install_link'                    => _n_noop( 'Begin installing plugin', 'Begin installing plugins', 'swifty-site' ),
            'activate_link'                   => _n_noop( 'Begin activating plugin', 'Begin activating plugins', 'swifty-site' ),
            'return'                          => __( 'Return to Required Plugins Installer', 'swifty-site' ),
            'plugin_activated'                => __( 'Plugin activated successfully.', 'swifty-site' ),
            'complete'                        => __( 'All plugins installed and activated successfully. %s', 'swifty-site' ), // %s = dashboard link.
            'nag_type'                        => 'updated' // Determines admin notice type - can only be 'updated', 'update-nag' or 'error'.
        )
    );

    // dorh Temp disable for w.org submit.
    stgmpa( $plugins, $config );
    //
    Swifty_TGM_Plugin_Activation::get_instance()->update_dismiss();
}

// Add plugin check for required plugins
add_action( 'stgmpa_register', 'swiftylife_register_required_plugins' );

// replace swiftyget:<name> with a proper download url
function swifty_get_download_url( $source )
{
    if( strpos( $source, 'swiftyget:' ) === 0 ) {
        $code = substr( $source, 10 );
        if( $code === 'ssd' ) {
            $url = 'https://stuff.swifty.online/stuff/data/get.php?do=get_version_data&code=ssd';
            $request = wp_remote_request( $url );
            if ( $request['response']['code'] === 200 ) {
                $data = json_decode( $request['body'], true );
                if( isset( $data['package'] ) ) {
                    return $data['package'];
                }
            }
        }
    }
    return $source;
}

add_filter( 'swifty_get_download_url', 'swifty_get_download_url' );