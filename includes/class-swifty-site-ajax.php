<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if( ! class_exists( 'Swifty_Site_Ajax' ) ) {

    /**
     * Class Swifty_Site_Ajax
     */
    class Swifty_Site_Ajax
    {
        /**
         * constructor, set actions and filters
         */
        public function __construct() {
            add_action( 'wp_ajax_swifty_get_active_swifty_plugins', array( $this, 'ajax_get_active_swifty_plugins' ) );
            add_action( 'wp_ajax_swifty_get_active_theme', array( $this, 'ajax_get_active_theme' ) );
            add_action( 'wp_ajax_swifty_install_and_activate_ssd', array( $this, 'ajax_install_and_activate_ssd' ) );
            add_action( 'wp_ajax_swifty_upgrade_ssd', array( $this, 'ajax_upgrade_ssd' ) );
            add_action( 'wp_ajax_swifty_get_plugin_versions', array( $this, 'ajax_get_plugin_versions' ) );
            add_action( 'wp_ajax_swifty_install_and_activate_plugin', array( $this, 'ajax_install_and_activate_plugin' ) );
        }

        function check_ajax_nonce() {
            return wp_verify_nonce( $_REQUEST[ 'ajax_nonce' ], 'swifty-site' );
        }

        /**
         * Get installed and active swifty plugin versions
         * Return json object
         * - succes (true / false)
         * - data.<plugin-slug> with curent version, repeated for each installed and active swifty plugin
         */
        function ajax_get_active_swifty_plugins() {
            if( $this->check_ajax_nonce() ) {

                $versions = apply_filters( 'swifty_active_plugins', array() );

                // check for plugin using plugin name
                if ( is_plugin_active( 'si-contact-form/si-contact-form.php' ) ) {
                    $versions[] = 'si-contact-form';
                }

                // check for plugin using plugin name
                if ( is_plugin_active( 'wordpress-seo-premium/wordpress-seo-premium.php' ) ) {
                    $versions[] = 'wordpress-seo-premium';
                } else if ( is_plugin_active( 'wordpress-seo/wordpress-seo.php' ) ) {
                    $versions[] = 'wordpress-seo';
                }

                wp_send_json_success( $versions );
            }
        }

        /**
         * Get name of currently active theme
         * Return json object
         * - succes (true / false)
         * - data.current_theme current active theme
         */
        function ajax_get_active_theme() {
            if( $this->check_ajax_nonce() ) {
                wp_send_json_success( array( 'current_theme' => get_stylesheet() ) );
            }
        }

        /**
         * Install and activate ssd theme
         * Return json object
         * - succes (true / false)
         * - data.current_theme current active theme
         */
        function ajax_install_and_activate_ssd() {
            if( $this->check_ajax_nonce() ) {

                global $swifty_lib_dir;
                if( isset( $swifty_lib_dir ) ) {
                    require_once $swifty_lib_dir . '/php/lib/class-swifty-theme-installer.php';
                }

                if( class_exists( 'Swifty_Theme_Installer' ) ) {
                    $theme_installer = new Swifty_Theme_Installer( 'swiftyget:ssd', new Automatic_Upgrader_Skin() );
                    $theme_installer->check_swifty_theme();
                }
                wp_send_json_success( array( 'current_theme' => get_stylesheet() ) );
            }
        }

        /**
         * Upgrade ssd theme
         * Return json object
         * - succes (true / false)
         * - data.current_theme current active theme
         */
        function ajax_upgrade_ssd() {
            if( $this->check_ajax_nonce() ) {

                global $swifty_lib_dir;
                if( isset( $swifty_lib_dir ) ) {
                    require_once $swifty_lib_dir . '/php/lib/class-swifty-theme-installer.php';
                }

                if( class_exists( 'Swifty_Theme_Installer' ) ) {
                    $theme_installer = new Swifty_Theme_Installer( 'swiftyget:ssd', new Automatic_Upgrader_Skin() );
                    $theme_installer->update_swifty_theme();
                }
                wp_send_json_success( array( 'current_theme' => get_stylesheet() ) );
            }
        }

        /**
         * Return json object with the swifty plugins as properties with each the following possible properties:
         * - succes (true / false)
         * - data.<plugin-slug>.status (active / not active / not installed)
         * - data.<plugin-slug>.version (available when at least installed)
         * - data.<plugin-slug>.update_status (when update found)
         * - data.<plugin-slug>.update_version (when update found)
         * - data.<plugin-slug>.update_slug
         * - data.<plugin-slug>.update_plugin
         * - data.<plugin-slug>.update_url (when update found)
         *
         * use this with:

        wp.ajax.post( 'update-plugin', {
        _ajax_nonce:     ssm_data.ajax_updates_nonce,
        plugin:          plugin,
        slug:            slug
        } )
        .done( succes method )
        .fail( fail method );

         */
        function ajax_get_plugin_versions() {

            if( $this->check_ajax_nonce() ) {
                $swifty_plugins_versions = apply_filters( 'swifty_active_plugin_versions', array() );

                // these responded to a filter, so they are all active...
                foreach( $swifty_plugins_versions as $plugin_name => $plugin_info ) {
                    $swifty_plugins_versions[ $plugin_name ][ 'status' ] = 'active';
                }

                // now check for Fast Secure Contact Form
                if( ! function_exists( 'get_plugins' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }
                $all_plugins = get_plugins();

                $ignore_wordpress_seo = false;
                if( key_exists( 'wordpress-seo-premium/wordpress-seo-premium.php', $all_plugins ) ) {
                    $ignore_wordpress_seo = true;
                }

                foreach( $all_plugins as $file_path => $plugin ) {
                    if( $file_path === 'si-contact-form/si-contact-form.php' ) {
                        $swifty_plugins_versions[ 'si-contact-form' ][ 'status' ] = 'active';
                        $swifty_plugins_versions[ 'si-contact-form' ][ 'version' ] = $plugin[ 'Version' ];
                    } else if( !$ignore_wordpress_seo && ( $file_path === 'wordpress-seo/wordpress-seo.php' ) ) {
                        $swifty_plugins_versions[ 'wordpress-seo' ][ 'status' ] = 'active';
                        $swifty_plugins_versions[ 'wordpress-seo' ][ 'version' ] = $plugin[ 'Version' ];
                    }
                }

                $update_plugins = get_site_transient( 'update_plugins' );
                if( isset( $update_plugins->response ) ) {
                    foreach( (array) $update_plugins->response as $file => $plugin ) {

                        if( isset( $swifty_plugins_versions[ $plugin->slug ] ) ) {
                            $status = 'update_available';
                            $update_file = $file;
                            if( current_user_can( 'update_plugins' ) ) {
                                $url = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . $update_file ), 'upgrade-plugin_' . $update_file );
                            }
                            $swifty_plugins_versions[ $plugin->slug ][ 'update_status' ] = $status;
                            $swifty_plugins_versions[ $plugin->slug ][ 'update_version' ] = $plugin->new_version;
                            $swifty_plugins_versions[ $plugin->slug ][ 'update_slug' ] = $plugin->slug;
                            $swifty_plugins_versions[ $plugin->slug ][ 'update_plugin' ] = $file;
                            $swifty_plugins_versions[ $plugin->slug ][ 'update_url' ] = $url;
                        }
                    }
                }

                if( current_user_can( 'update_themes' ) ) {
                    $update_themes = get_site_transient( 'update_themes' );
                    if( isset( $update_themes->response ) ) {
                        foreach( (array) $update_themes->response as $theme_slug => $update_found ) {
                            if( isset( $swifty_plugins_versions[ $theme_slug ] ) ) {
                                $defaults = array( 'new_version' => '', 'url' => '', 'package' => '' );
                                $update_found = wp_parse_args( $update_found, $defaults );

                                $status = 'update_available';
                                $swifty_plugins_versions[ $theme_slug ][ 'update_status' ] = $status;
                                $swifty_plugins_versions[ $theme_slug ][ 'update_version' ] = $update_found[ 'new_version' ];
                                $swifty_plugins_versions[ $theme_slug ][ 'update_slug' ] = $theme_slug;
                            }
                        }
                    }
                }

                $installed_plugins = get_plugins();
                Swifty_TGM_Plugin_Activation::get_instance()->populate_file_path();

                // we always want information for these plugins
                foreach( array( 'swifty-' . 'content-creator', 'swifty-page-manager' ) as $plugin_name ) {

                    if( ! isset( $swifty_plugins_versions[$plugin_name]) ) {

                        foreach( Swifty_TGM_Plugin_Activation::get_instance()->plugins as $plugin ) {
                            if( $plugin[ 'slug' ] === $plugin_name ) {

                                if( isset( $installed_plugins[ $plugin[ 'file_path' ] ] ) ) {
                                    $swifty_plugins_versions[$plugin_name] =
                                        array(
                                            'status' => 'not active',
                                            'version' => $installed_plugins[ $plugin[ 'file_path' ] ]['Version']);
                                } else {
                                    $swifty_plugins_versions[$plugin_name] = array( 'status' => 'not installed' );
                                }
                            }
                        }
                    }
                }

                wp_send_json_success( $swifty_plugins_versions );
            }
        }

        /**
         * Install and activate a plugin that is registered in the $plugins array of Swifty_TGM_Plugin_Activation
         * - plugin_slug slug of the plugin to find and install
         * return json object with success containing true or false
         */
        function ajax_install_and_activate_plugin() {
            if( $this->check_ajax_nonce() ) {

                // IMPACT_ON_SECURITY
                $plugin_slug = $_POST[ 'plugin_slug' ];

                if ( Swifty_TGM_Plugin_Activation::$instance->install_or_activate_plugin( $plugin_slug, FALSE )
                    && Swifty_TGM_Plugin_Activation::$instance->install_or_activate_plugin( $plugin_slug, TRUE )
                ) {
                    wp_send_json_success();
                } else {
                    wp_send_json_error();
                }
            }
        }
    }

    new Swifty_Site_Ajax();
}
