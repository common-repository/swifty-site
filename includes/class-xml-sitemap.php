<?php if( ! defined( 'ABSPATH' ) ) {
    die();
}

// Main class, based on https://wordpress.org/plugins/simple-wp-sitemap

class Swifty_XmlSitemap
{

// SS_DOC_ARTICLE
// id_sol: 6982
// id_fd: 11000022497
// id_parent_sol: 6733 // Actions and filters in Swifty Site
// title: Filter: swifty_update_sitemap_enabled
// tags: Swifty Site,filter
// Swifty Site has an option to prevent the creation of a XML site map which is normally created and updated after changing posts.<br>
// <br>
// By applying this filter you can prevent the creation of it when you return false.<br>
// <br>
// Example:<br>
// <pre lang="php"><nobr>
// add_filter( 'swifty_update_sitemap_enabled', '__return_false' );
// </pre lang="php">
// SS_DOC_END

    // Updates the sitemaps
    public static function updateSitemaps( $post_id )
    {
        if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // ignore revision changes, autosave triggers this action
        if( wp_is_post_revision( $post_id ) )
            return;

        if( apply_filters( 'swifty_update_sitemap_enabled', true ) ) {
            require_once( 'class-xml-map-builder.php' );

            // we have to store the global post because it is changed in the called class
            // this is a problem when called from the save_post action while restoring a revision
            $store_current_post = null;
            if( isset( $GLOBALS[ 'post' ] ) ) {
                $store_current_post = $GLOBALS[ 'post' ];
            }
            new Swifty_XmlMapBuilder( 'generate' );
            if( $store_current_post ) {
                $GLOBALS[ 'post' ] = $store_current_post;
            }
        }
    }

    // Delete the files sitemap.xml on deactivate
    public static function removeSitemaps()
    {
        if( apply_filters( 'swifty_update_sitemap_enabled', true ) ) {
            require_once( 'class-xml-map-builder.php' );
            new Swifty_XmlMapBuilder( 'delete' );
        }
    }
}

add_action( 'deleted_post', array( 'Swifty_XmlSitemap', 'updateSitemaps' ) );
add_action( 'save_post', array( 'Swifty_XmlSitemap', 'updateSitemaps' ) );
add_action( 'swifty_update_sitemap', array( 'Swifty_XmlSitemap', 'updateSitemaps' ) );
register_activation_hook( SWIFTY_MENU_PLUGIN_FILE, array( 'Swifty_XmlSitemap', 'updateSitemaps' ) );
register_deactivation_hook( SWIFTY_MENU_PLUGIN_FILE, array( 'Swifty_XmlSitemap', 'removeSitemaps' ) );