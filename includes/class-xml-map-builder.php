<?php if( ! defined( 'ABSPATH' ) ) {
    die();
}

/*
 * The sitemap creating class, based on https://wordpress.org/plugins/simple-wp-sitemap
 */

class Swifty_XmlMapBuilder
{
    private $xml;
    private $file;
    private $url;
    private $homeUrl;

    // Constructor, the only public function this class has
    public function __construct( $command )
    {

        $info = pathinfo( SWIFTY_MENU_PLUGIN_FILE );
        $this->url = esc_url( plugins_url() . '/' . basename( $info[ 'dirname' ] ) );
        $this->homeUrl = esc_url( get_home_url() . ( substr( get_home_url(), -1 ) === '/' ? '' : '/' ) );

        switch( $command ) {
            case 'generate':
                $this->generateSitemaps();
                break;
            case 'delete':
                $this->deleteSitemaps();
        }
    }

    // Generates the maps
    private function generateSitemaps()
    {
        $doXmlOurselves = true;
        if( defined( 'WPSEO_VERSION' ) && class_exists( 'WPSEO_Options' ) ) {
            $yoastXmlOption = WPSEO_Options::get_option( 'wpseo_xml' );

//            $yoastXmlOption[ 'enablexmlsitemap' ] = true;
//            update_option( 'wpseo_xml', $yoastXmlOption );

            if( $yoastXmlOption[ 'enablexmlsitemap' ] ) {
                // Yoast XM Sitemap is anable, si let's use Yoast's instead of ours.
                $doXmlOurselves = false;
            }
        }

        if( $doXmlOurselves ) {
            $this->getContent();

            $this->writeToFile( $this->xml, 'xml' );
        } else {
            $this->deleteFile( 'xml' );
        }
    }

    // Deletes the maps
    private function deleteSitemaps()
    {
        $this->deleteFile( 'xml' );
    }

    // Returns an xml string
    private function getXml( $link, $date )
    {
        return "<url>\n\t<loc>$link</loc>\n\t<lastmod>$date</lastmod>\n</url>\n";
    }

    // Creates the actual sitemaps content, and querys the database
    private function getContent()
    {
        $q = new WP_Query( 'post_type=any&posts_per_page=-1' );
        $xml = sprintf( "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<?xml-stylesheet type=\"text/css\" href=\"%s/css/xml.css\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd\">\n", $this->url );
        $posts = '';
        $pages = '';
        $homePage = false;

        if( $q->have_posts() ) {
            while( $q->have_posts() ) {
                $q->the_post();

                $link = esc_url( get_permalink() );
                $date = esc_html( get_the_modified_date( 'Y-m-d\TH:i:sP' ) );

                if( $link === $this->homeUrl ) {
                    $xml .= $this->getXml( $link, $date );
                    $homePage = true;
                } elseif( 'page' === get_post_type() ) {
                    $pages .= $this->getXml( $link, $date );
                } else { // posts (also all custom post types are added here)
                    $posts .= $this->getXml( $link, $date );
                }
            }
        }

        $localArr = $this->mergeArraysAndGetOtherPages( $posts, $pages, $homePage );

        $this->xml = sprintf( "%s%s</urlset>", $xml, $localArr );
        wp_reset_postdata();
    }

    // Merges the arrays with post data into strings and gets user submitted pages, categories, tags and author pages
    private function mergeArraysAndGetOtherPages( $posts, $pages, $homePage )
    {
        $xml = '';

        if( ! $homePage ) { // if homepage isn't found in the query add it here (for instance if it's not a real "page" it wont be found)
            $timezone = get_option( 'timezone_string' );
            if( $timezone ) {
                date_default_timezone_set( $timezone );
            }
            $date = date( 'Y-m-d\TH:i:sP' );
            $xml .= $this->getXml( $this->homeUrl, $date );
        }

        if( $posts ) {
            $xml .= $posts;
        }

        if( $pages ) {
            $xml .= $pages;
        }

        return $xml;
    }

    // Sets up file paths to home directory
    private function setFile( $fileType )
    {
        $this->file = sprintf( "%s%ssitemap.%s", ABSPATH, ( substr( ABSPATH, -1 ) === '/' ? '' : '/' ), $fileType );
    }

    // Creates sitemap files and overrides old ones if there's any
    private function writeToFile( $data, $fileType )
    {
        $this->setFile( $fileType );
        try {
            $fp = fopen( $this->file, 'w' );
            if( file_exists( $this->file ) ) {
                fwrite( $fp, $data );
                fclose( $fp );
            }
        } catch( Exception $ex ) {
            die();
        }
    }

    // Deletes the sitemap files
    private function deleteFile( $fileType )
    {
        $this->setFile( $fileType );
        try {
            unlink( $this->file );
        } catch( Exception $ex ) {
            die();
        }
    }
}