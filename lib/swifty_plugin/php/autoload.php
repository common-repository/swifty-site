<?php

if( ! function_exists( 'swifty_read_text' ) ) {

    function swifty_read_text( $filename ) {
        return call_user_func( 'file_get_contents', $filename);
    }
}

if( ! function_exists( 'swifty_autoload_lib_helper' ) ) {

    /**
     * turns out that glob is not always working:
     * http://php.net/manual/en/function.glob.php#102691
     * so we use a replacement
     *
     * @param $pattern
     * @return array|bool
     */
    function swifty_glob( $pattern )
    {
        $split = explode( '/', str_replace( '\\', '/', $pattern ) );
        $mask = array_pop( $split );
        $path = implode( '/', $split );
        if( ( $dir = opendir( $path ) ) !== false ) {
            $glob = array();
            while( ( $file = readdir( $dir ) ) !== false ) {
                // Match file mask
                if( fnmatch( $mask, $file ) && is_dir( "$path/$file" ) ) {
                    $glob[] = "$path/$file";
                }
            }
            closedir( $dir );
            return $glob;
        } else {
            return false;
        }
    }

    /**
     * Look for newest swiftylib in plugins and themes folders
     *
     * @param $file_path
     */
    function swifty_autoload_lib_helper_main( $file_path )
    {
        global $swifty_lib_dir;
        if( ! isset( $swifty_lib_dir ) ) {
            $best_version = -1;
            $best_dir = '';
            $directories = swifty_glob( WP_PLUGIN_DIR . '/swifty*' );
            swifty_autoload_lib_helper( $directories, '/lib/swifty_plugin', $best_version, $best_dir );
            if( strpos( get_stylesheet(), 'swifty-' ) === 0 ) {
                $directories = swifty_glob( get_theme_root() . '/swifty*' );
                swifty_autoload_lib_helper( $directories, '/ssd/lib/swifty_plugin', $best_version, $best_dir );
            }
            $swifty_lib_dir = $best_dir;
//            echo 'BEST... #####' . $best_dir . '#####' . $best_version . '<br>';
        }
        if( $swifty_lib_dir !== '' ) {
            require_once $swifty_lib_dir . $file_path;
        }
    }

    /**
     * compare swifty lib version in given directories and return newest
     *
     * @param $directories
     * @param $version_path
     * @param $best_version
     * @param $best_dir
     */
    function swifty_autoload_lib_helper( $directories, $version_path, &$best_version, &$best_dir )
    {
        if( is_array( $directories ) ) {
            foreach( $directories as $dir ) {
                $file = $dir . $version_path . '/swifty_version.txt';
                $version = -1;
                if( file_exists( $file ) ) {
                    $version = floatval( swifty_read_text( $file ) );
                }
//                echo '#####' . $dir . '#####' . $version . '<br>';
                // split 'swifty-content-' . 'creator' to prevent being found when looking for translations
                if( $version > $best_version || ( $version === $best_version && basename( $dir ) === ( 'swifty-content-' . 'creator' ) ) ) {
                    $best_version = $version;
                    $best_dir = $dir . $version_path;
//                    echo 'BETTER #####' . $dir . '#####' . $version . '<br>';
                }
            }
        }
    }

    /**
     * Check if swiftylib classes are needed and find newest version of lib before instantiating
     *
     * @param $class_name
     */
    function swifty_autoload_function( $class_name )
    {
        if( $class_name === 'LibSwiftyPlugin' ) {
            swifty_autoload_lib_helper_main( '/php/lib_swifty_plugin.php' );

            if( is_null( LibSwiftyPlugin::get_instance() ) ) {
                new LibSwiftyPlugin();
            }
        }
        if( $class_name === 'LibSwiftyPluginView' ) {
            swifty_autoload_lib_helper_main( '/php/lib_swifty_plugin_view.php' );

            if( is_null( LibSwiftyPluginView::get_instance() ) ) {
                new LibSwiftyPluginView();
            }
        }
    }

    spl_autoload_register( 'swifty_autoload_function' );

}