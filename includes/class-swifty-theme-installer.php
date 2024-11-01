<?php

if( ! class_exists( 'WP_Upgrader' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
}

if( ! class_exists( 'Swifty_Theme_Installer' ) ) {

    /**
     * Class Swifty_Theme_Installer
     */
    class Swifty_Theme_Installer extends Theme_Upgrader
    {

        public $package = null;

        protected $theme_name = 'swifty-site-designer';

        public function __construct( $package, $skin = null )
        {
            parent::__construct( $skin );

            $this->package = $package;
        }

        public function check_swifty_theme()
        {
            if( $this->package && ( $this->theme_name !== get_stylesheet() ) ) {
                $this->do_install_swifty_theme();
            }
        }

        public function do_install_swifty_theme()
        {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/misc.php' );

            $theme_object = wp_get_theme( $this->theme_name );
            if( ! $theme_object->exists() ) {

                $this->package = apply_filters( 'swifty_get_download_url', $this->package );

                $result = $this->install( $this->package );
                if( ! $result || is_wp_error( $result ) ) {
                    return $result;
                }
            }
            switch_theme( $this->theme_name );
        }
        
        public function update_swifty_theme() {
            $this->package = apply_filters( 'swifty_get_download_url', $this->package );
            $result = $this->upgrade( $this->theme_name );
            if( ! $result || is_wp_error( $result ) ) {
                return $result;
            }
        }
            
    }
}
