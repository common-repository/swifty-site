<?php

if( ! class_exists( 'Swifty_Theme_Uninstaller' ) ) {

    /**
     * Class Swifty_Theme_Uninstaller
     * Remove options when swifty-site-designer is no longer installed. This is not possible from the theme itself, so we
     * use the action that is called when a theme is deleted. This means the options will be removed when the theme is
     * uninstalled before the other swifty plugins.
     */
    class Swifty_Theme_Uninstaller
    {
        protected $theme_name = 'swifty-site-designer';

        // Option to delete, names can contain % as wildcard
        protected $option_names = array( 'swifty_css_location', 'auto_page_titles', 'ssd_show_wizard' );

        // Site options to delete
        protected $site_option_names = array();

        // remove post meta data
        protected $post_meta_names = array();

        // posttypes to delete
        protected $post_types = array( 'swifty_area' );

        protected $areas = array( 'page', 'main', 'topbar', 'header', 'navbar', 'sidebar', 'extrasidebar', 'footer', 'bottombar' );

        /**
         * Swifty_Theme_Uninstaller constructor.
         * Track the action that is called when a theme is deleted.
         */
        public function __construct() {
            add_action( 'delete_site_transient_update_themes', array( $this, 'hook_delete_site_transient_update_themes' ) );
        }

        /**
         *
         */
        protected function init_meta_names() {
            foreach( $this->areas as $area ) {
                $this->post_meta_names[] = 'spm_' . $area .'_template';
                $this->post_meta_names[] = 'spm_' . $area .'_visibility';
            }
        }

        /**
         * Figure out if swifty-site-designer is installed. If not then delete options etc.
         */
        public function hook_delete_site_transient_update_themes() {

            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/misc.php' );

            $theme_object = wp_get_theme( $this->theme_name );
            $theme_object->cache_delete();
            if( ! $theme_object->exists() ) {

                $this->init_meta_names();

                // this is not installed, so we can simply remove the settings that are not in use
                $this->remove_blog_options();
            }
        }

        /**
         * Multisite: remove options for each blog, otherwise remove options
         */
        public function remove_blog_options() {

            if ( !is_multisite() ) {
                $this->remove_options();
            } else {
                global $wpdb;

                $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

                foreach( $blog_ids as $blog_id ) {
                    switch_to_blog( $blog_id );

                    $this->remove_options();
                }

                restore_current_blog();
            }
        }

        /**
         * Remove options from the current blog
         */
        protected function remove_options() {

            // start removing options
            foreach( $this->site_option_names as $site_option_name ) {
                delete_site_option( $site_option_name );
            }

            global $wpdb;

            // remove options
            foreach( $this->option_names as $option_name ) {
                // use LIKE, to also allow % wildcard
                $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '{$option_name}'" );
            }

            // remove all post meta data
            foreach( $this->post_meta_names as $post_meta_name ) {
                $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '{$post_meta_name}'" );
            }

            foreach( $this->post_types as $post_type ) {
                $post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = '%s'", $post_type ) );
                foreach( $post_ids as $post_id ) {
                    // use this wp function to also delete the meta data
                    wp_delete_post( $post_id, true );
                }
            }
        }
    }

    new Swifty_Theme_Uninstaller();
}