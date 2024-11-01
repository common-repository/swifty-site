<?php

if( ! class_exists( 'SwiftyImageFunctions' ) ) {

    class SwiftyImageFunctions
    {
        /**
         * do we want a swifty-large image size? if so then use it instead of 'large'
         *
         * @var string
         */
        public static $image_size = 'swifty_content';

        public static $image_sizes_viewport_width = array( 320, 375, 640, 800, 1024, 1366, 1920, 8888 );

        /**
         * Get the table name (including prefix) for the cache of automatically determined image sizes.
         *
         * @return string
         */
        public static function get_image_sizes_table_name() {
            global $wpdb;

            return $wpdb->prefix . 'swifty_image_sizes_cache';
        }

        public static function image_sizes_to_generate() {
            return array(
                array( 'mobile_half', 160, __( 'Mobile half', 'swifty-site' ) ),
                array( 'mobile_small', 320, __( 'Mobile small', 'swifty-site' ) ),
                array( 'mobile_regular', 375, __( 'Mobile regular', 'swifty-site' ) ),
                array( 'mobile_landscape', 480, __( 'Mobile landscape', 'swifty-site' ) ),
                array( 'desktop_smallest', 640, __( 'Desktop smallest', 'swifty-site' ) ),
                array( 'desktop_small', 800, __( 'Desktop small', 'swifty-site' ) ),
                array( 'desktop_medium', 1024, __( 'Desktop medium', 'swifty-site' ) ),
                array( 'desktop_large', 1366, __( 'Desktop large', 'swifty-site' ) ),
                array( 'full_hd', 1920, __( 'Full HD', 'swifty-site' ) )
            );
        }

        // dorh Duplicate code

        public static function get_img_vars( $url, $attach_id = -123, $atts = array(), $id_post = -1, $image_src = '' )
        {
            $src_word = 'src';
            $script = '';
            $responsive = '';
            $image_size = SwiftyImageFunctions::$image_size;

            // use swifty responsive solution
            if( get_option( 'ss2_hosting_name' ) === 'AMH' ) {
                $src_word = 'swifty_src';
                if( strpos( $url, 'swifty=1' ) === false ) {
                    $url .= ( parse_url( $url, PHP_URL_QUERY ) ? '&' : '?' ) . 'swifty=1';
                }
                $script = "<script>if( typeof swifty_add_exec === 'function' ) { swifty_add_exec( { 'fn': 'swifty_checkImages' } ); }</script>";
            } else {
                $responsive = SwiftyImageFunctions::responsive_wp( $url, $atts, $id_post, $image_src );
                if( $image_size !== 'full' ) {
                    if( $attach_id === -123 ) {
                        $attach_id = SwiftyImageFunctions::get_attachment_id_from_url( $url );
                    }
                    if( $attach_id ) {
                        $image_attributes = SwiftyImageFunctions::get_attachment_image_src_wp( $attach_id, $image_size );
                        $url = $image_attributes[ 0 ];
                    }
                }
            }

            return array( $src_word, $url, $script, $responsive );
        }

        public static function get_img_tag( $url, $alt = '', $go_to_url = false, $href = '', $target = '', $viewer = 'nothing', $atts = array(), $class = '', $id_post = -1, $image_src = '' )
        {
            $url_a = $url;
            $image_size = SwiftyImageFunctions::$image_size;

            list( $src_word, $url, $script, $responsive ) = SwiftyImageFunctions::get_img_vars( $url, -123, $atts, $id_post, $image_src );

            // use swifty responsive solution
            if( get_option( 'ss2_hosting_name' ) === 'AMH' ) {
                $src_word = 'swifty_src';
                if( strpos( $url, 'swifty=1' ) === false ) {
                    $url .= ( parse_url( $url, PHP_URL_QUERY ) ? '&' : '?' ) . 'swifty=1';
                }
                $script = "<script>if( typeof swifty_add_exec === 'function' ) { swifty_add_exec( { 'fn': 'swifty_checkImages' } ); }</script>";
            } else {
                $responsive = SwiftyImageFunctions::responsive_wp( $url, $atts, $id_post, $image_src );
                if( $image_size !== 'full' ) {
                    $attach_id = SwiftyImageFunctions::get_attachment_id_from_url( $url );
                    if( $attach_id ) {
                        $image_attributes = SwiftyImageFunctions::get_attachment_image_src_wp( $attach_id, $image_size );
                        $url = $image_attributes[ 0 ];
                    }
                }
            }

            if( $viewer === 'nothing' ) {
                $html = '<img ' . $src_word . '="' . $url . '"' . $responsive . ' alt="' . $alt . '" class="' . $class . '">' . $script;
            } else {
                $html =
                    '<a ' .
                    'href="' . ( $go_to_url ? $href : $url_a ) . '" ' .
                    'class="swc_image_link" ' .
                    ( $go_to_url && $target ? 'target="' . $target . '" ' : '' ) .
                    '>' .
                    '<img ' . $src_word . '="' . $url . '"' . $responsive . ' alt="' . $alt . '" ' .
                    ( $go_to_url ? '' : 'onclick="swcThumbnailClick( this, \'' . $viewer . '\' );return false;"' ) .
                    ' class="' . $class . '">' .
                    '</a>' . $script;
            }

            if( isset( $atts[ 'caption' ] ) && ! empty( $atts[ 'caption' ] ) ) {
                $html = '<figure class="wp-caption ' . $class . '">' . $html . '<figcaption class="wp-caption-text"><span><span>' . $atts[ 'caption' ] . '</span></span></figcaption></figure>';
            }

            return $html;
        }

        /**
         * wrapper around wp_get_attachment_image_src to create the wanted size if not available
         *
         * @param $attachment_id
         * @param string $size
         * @param bool|false $icon
         * @return array|false
         */
        public static function get_attachment_image_src_wp( $attachment_id, $size = 'thumbnail', $icon = false ) {
            $image = wp_get_attachment_image_src( $attachment_id, $size, $icon );

            // we want to use $this->image_size unless image is smaller
            global $content_width;
            if( ! $image || ( ! $image[ 3 ] && ( $content_width <= $image[ 1 ] ) ) ) {

                // create list of available sizes and verify requested size is in it
                $sizes = array();
                foreach ( get_intermediate_image_sizes() as $s ) {
                    $sizes[$s] = 1;
                }
                $sizes = apply_filters( 'intermediate_image_sizes_advanced', $sizes, null );

                if(  array_key_exists($size, $sizes ) ) {
                    // so we regenerate the images, this only happens once
                    $fullsizepath = get_attached_file( $attachment_id );
                    if( $fullsizepath && file_exists( $fullsizepath ) ) {

                        require_once( ABSPATH . 'wp-admin/includes/image.php' );

                        $metadata = wp_generate_attachment_metadata( $attachment_id, $fullsizepath );

                        if( ! is_wp_error( $metadata ) ) {
                            wp_update_attachment_metadata( $attachment_id, $metadata );

                            $image = wp_get_attachment_image_src( $attachment_id, $size, $icon );
                        }
                    }
                }
            }
            return $image;
        }

        /**
         * Get wp 4.4 responsive solution, return the srcset and sizes attributes in a string which starts with
         * a space
         *
         * @param $url
         * @param $atts: the attributes of the related shortcode.
         * @param $id_post: the post/page id used for this image
         * @param $image_src: optional, image url used in DB
         * @return string
         */
        public static function responsive_wp( $url, $atts = array(), $id_post = -1, $image_src = '' )
        {
            $responsive = '';

            $srcset = false;
            $sizes = false;

            $image_size = SwiftyImageFunctions::$image_size;

            // only for wp 4.4+
            if( isset( $url ) && function_exists( 'wp_calculate_image_srcset' ) ) {
                $attach_id = SwiftyImageFunctions::get_attachment_id_from_url( $url );

                if( $attach_id ) {
                    $image = SwiftyImageFunctions::get_attachment_image_src_wp( $attach_id, $image_size, false );

                    if( $image ) {
                        list( $src, $width, $height ) = $image;

                        $image_meta = wp_get_attachment_metadata( $attach_id );

                        if( is_array( $image_meta ) ) {
                            $size_array = array( absint( $width ), absint( $height ) );
                            // wp_calculate_image_srcset will only return sizes smaller than 1600
                            $srcset = wp_calculate_image_srcset( $size_array, $src, $image_meta, $attach_id );
                            $sizes = wp_calculate_image_sizes( $size_array, $src, $image_meta, $attach_id );

                            if( array_key_exists( 'swc_cssid', $atts ) && $atts[ 'swc_cssid' ] . '' !== '' ) {
                                $sizes = SwiftyImageFunctions::get_image_responsive_sizes( $atts, $sizes, $width, $id_post, $image_src ? $image_src : $url );
                            }

                        }
                    }
                }
            }

            if( $sizes && $srcset ) {
                $responsive = ' srcset="' . esc_attr( $srcset ) . '" sizes="' . esc_attr( $sizes ) . '"';
            }

            return $responsive;
        }

        /**
         * Get the determined image sizes from the database and return a sizes attribute to be added to the srcset in the image.
         *
         * @param $atts: the attributes of the related shortcode.
         * @param $ori_sizes: The previous sizes attribute.
         * @param $width: the default width to be placed in sizes.
         * @param $id_post: the post/page id used for this image
         * @return string: the sizes attribute.
         *
         */
        public static function get_image_responsive_sizes( $atts, $ori_sizes, $width, $id_post = -1, $image_src = '' ) {
            global $wpdb;

            $sizes = $ori_sizes;

            if( intval( get_option( 'scc_img_db_version' ) ) > 0 ) {

                $sizes = '';
                $found = false;

                if( $id_post === -1 ) {
                    $id_post = get_the_ID();
                }
                $id_asset = 'c' . $atts[ 'swc_cssid' ];

                $show_errors = $wpdb->show_errors;

                if( $show_errors ) {
                    $wpdb->hide_errors();
                }

                $determined_sizes = $wpdb->get_results( $wpdb->prepare(
                    'SELECT w_asset, w_viewport FROM ' . SwiftyImageFunctions::get_image_sizes_table_name() . ' WHERE id_post = %d AND id_asset = %s AND image_src = %s AND w_asset > 0 ORDER BY w_viewport DESC ',
                    $id_post,
                    $id_asset,
                    $image_src
                ) );

                if( $wpdb->last_error ) {
                    update_option( 'scc_img_db_version', 0 );
                }

                if( $show_errors ) {
                    $wpdb->show_errors();
                }

                $last_w_asset = -1;

                if( $determined_sizes ) {
                    foreach( $determined_sizes as $determined_size ) {
                        $found = true;
                        if( $determined_size->w_viewport == 8888 ) {
                            $width = $determined_size->w_asset;
                        } else {
                            if( $last_w_asset === -1 || $last_w_asset != $determined_size->w_asset ) {
                                $sizes = sprintf( '(max-width: %1$dpx) %2$dpx, ', $determined_size->w_viewport, $determined_size->w_asset ) . $sizes;
                            }
                        }
                        $last_w_asset = $determined_size->w_asset;
                    }
                }

                if( $found ) {
                    $sizes .= sprintf( '%1$dpx', $width );
                } else {
                    $sizes = $ori_sizes;
                }
            }

            return $sizes;
        }

        /**
         * return id from a given attachment url
         *
         * $attachment_url contains the attachment url
         *
         */
        public static function get_attachment_id_from_url( $attachment_url )
        {
            global $wpdb;

            $attachment_id = false;

            // If there is no url, return.
            if( $attachment_url === '' ) {
                return;
            }

            // Get the upload directory paths
            $upload_dir_paths = wp_upload_dir();

            // Make sure the upload path base directory exists in the attachment URL, to verify that we're working with a media library image
            if( strpos( $attachment_url, $upload_dir_paths[ 'baseurl' ] ) !== false ) {
                // If this is the URL of an auto-generated thumbnail, get the URL of the original image
                $attachment_url = preg_replace( '/-\d+x\d+(?=\.(jpg|jpeg|png|gif)$)/i', '', $attachment_url );

                // Remove the upload path base directory from the attachment URL
                $attachment_url = str_replace( $upload_dir_paths[ 'baseurl' ] . '/', '', $attachment_url );

                // Finally, run a custom database query to get the attachment ID from the modified attachment URL
                $attachment_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT wposts.ID FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta WHERE wposts.ID = wpostmeta.post_id AND wpostmeta.meta_key = '_wp_attached_file' AND wpostmeta.meta_value = '%s' AND wposts.post_type = 'attachment'",
                        $attachment_url
                    )
                );
            }
            return $attachment_id;
        }

        public static function create_image_sizes_table() {
            $scc_img_db_version = 2; // Increase if database structure has changed.

            if( get_option( 'ss2_hosting_name' ) !== 'AMH' ) {
                if( intval( get_option( 'scc_img_db_version' ) ) < $scc_img_db_version ) {
                    global $charset_collate;

                    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

                    $sql_create_table = "CREATE TABLE " . SwiftyImageFunctions::get_image_sizes_table_name() . " (
                    id bigint(20) unsigned NOT NULL auto_increment,
                    id_post bigint(20) unsigned NOT NULL default '0',
                    id_asset varchar(50) NOT NULL,
                    image_src varchar(2000) NOT NULL,
                    w_viewport int(11) NOT NULL default '0',
                    w_asset int(11) NOT NULL default '-1',
                    w_determine int(11) NOT NULL default '-1',
                    PRIMARY KEY  (id),
                    KEY post_asset_src (id_post,id_asset,image_src)
                    ) $charset_collate; ";

                    dbDelta( $sql_create_table );

                    update_option( 'scc_img_db_version', $scc_img_db_version );
                }
            }
        }

        /**
         * Trigger the determination of the image sizes on a page.
         */
        public static function determine_image_sizes( $id_post, $ids ) {
            if( get_option( 'ss2_hosting_name' ) !== 'AMH' ) {
                global $wpdb;

                SwiftyImageFunctions::create_image_sizes_table();

                // IMPACT_ON_SECURITY

                // Add new sizes to be determined
                foreach( $ids as $id ) {
                    $id_asset = $id[ 'id' ];
                    $image_src = $id[ 'src' ];
                    foreach( SwiftyImageFunctions::$image_sizes_viewport_width as $viewport_width ) {
                        $existing_id = $wpdb->get_var( $wpdb->prepare(
                            'SELECT id FROM ' . SwiftyImageFunctions::get_image_sizes_table_name() . ' WHERE id_post = %d AND id_asset = %s AND image_src = %s AND w_viewport = %d ',
                            $id_post,
                            $id_asset,
                            $image_src,
                            $viewport_width
                        ) );

                        if( $existing_id > 0 ) {
                            // Update.
                            $where = array(
                                'id_post' => $id_post,
                                'id_asset' => $id_asset,
                                'image_src' => $image_src,
                                'w_viewport' => $viewport_width
                            );

                            $data = array(
                                'w_determine' => -1
                            );

                            $column_formats = array(
                                '%d'
                            );

                            $where_formats = array(
                                '%d',
                                '%s',
                                '%s',
                                '%d'
                            );

                            $wpdb->update( SwiftyImageFunctions::get_image_sizes_table_name(), $data, $where, $column_formats, $where_formats );
                        } else {
                            // Insert.

                            $data = array(
                                'id_post' => $id_post,
                                'id_asset' => $id_asset,
                                'image_src' => $image_src,
                                'w_viewport' => $viewport_width,
                                'w_asset' => -1,
                                'w_determine' => -1
                            );

                            $column_formats = array(
                                '%d',
                                '%s',
                                '%s',
                                '%d',
                                '%d',
                                '%d'
                            );

                            $wpdb->insert( SwiftyImageFunctions::get_image_sizes_table_name(), $data, $column_formats );
                        }
                    }
                }

                // reset post meta state for successfully retrieval of needed asset ids
                update_post_meta( $id_post, 'swifty_determine_image_sizes', '' );
            }
        }

        /**
         * Update the determined size of an image on a page.
         */
        public static function determine_image_set_size( $id_post, $id_asset, $viewport_width, $w_asset, $image_src ) {
            if( get_option( 'ss2_hosting_name' ) !== 'AMH' ) {
                global $wpdb;

                SwiftyImageFunctions::create_image_sizes_table();

                if( $w_asset === -3 ) {
                    // IMPACT_ON_SECURITY
                    $where = array(
                        'id_post' => $id_post,
                        'id_asset' => $id_asset
                    );

                    $where_formats = array(
                        '%d',
                        '%s'
                    );

                    $wpdb->delete( SwiftyImageFunctions::get_image_sizes_table_name(), $where, $where_formats );
                } else {
                    // IMPACT_ON_SECURITY

                    $where = array(
                        'id_post' => $id_post,
                        'id_asset' => $id_asset,
                        'image_src' => $image_src,
                        'w_viewport' => $viewport_width
                    );

                    $data = array(
                        'w_asset' => $w_asset,
                        'w_determine' => $w_asset
                    );

                    $column_formats = array(
                        '%d',
                        '%d'
                    );

                    $where_formats = array(
                        '%d',
                        '%s',
                        '%s',
                        '%d'
                    );

                    $wpdb->update( SwiftyImageFunctions::get_image_sizes_table_name(), $data, $where, $column_formats, $where_formats );
                }
            }
        }

        /**
         * Get the needed determination of the image sizes on a page.
         */
        public static function get_determine_image_sizes( $id_post ) {
            if( get_option( 'ss2_hosting_name' ) !== 'AMH' ) {
                global $wpdb;

                if( intval( get_option( 'scc_img_db_version' ) ) > 0 ) {
                    // IMPACT_ON_SECURITY

                    $show_errors = $wpdb->show_errors;

                    if( $show_errors ) {
                        $wpdb->hide_errors();
                    }

//            $sql = $wpdb->prepare( 'SELECT * FROM ' . SwiftyImageFunctions::get_image_sizes_table_name() . ' WHERE id_post=%d AND w_determine=-1 ORDER BY id_post, w_viewport DESC', $id_post );
                    $sql = 'SELECT * FROM ' . SwiftyImageFunctions::get_image_sizes_table_name() . ' WHERE w_determine=-1 ORDER BY id_post, w_viewport DESC';

                    $sizes = $wpdb->get_results( $sql );

                    if( $wpdb->last_error ) {
                        update_option( 'scc_img_db_version', 0 );
                    }

                    if( $show_errors ) {
                        $wpdb->show_errors();
                    }

                    if( $sizes ) {
                        foreach( $sizes as $size ) {
                            $size->url = get_page_link( $size->id_post );
                        }
                    }

                    return $sizes;
                }
            }

            return array();
        }

        /**
         * the sizes that we want to create for new image attachments
         *
         * @param array $sizes
         * @param string $option_attachmentsizes
         * @return array
         */
        public static function intermediate_image_sizes_advanced( $sizes, $option_attachmentsizes ) {

            // just return the wp sizes, no changes
            if( $option_attachmentsizes === 'wp' ) {
                return $sizes;
            }

            // remove wp sizes (default sizes are: thumbnail, medium, medium_large, large)
            if( $option_attachmentsizes === 'swifty' ) {
                $size_names = array_keys($sizes);
                // do not remove these sizes
                $size_names = array_diff($size_names, array( 'thumbnail', 'swifty_content' ) );

                foreach($size_names as $key => $value ) {
                    unset( $sizes[ $value ] );
                }
            }

            // add swifty sizes ( width, height, crop )
            foreach( SwiftyImageFunctions::image_sizes_to_generate() as $size ) {
                $sizes[ $size[ 0 ] ] = array( 'width' => $size[ 1 ], 'height' => 9999, 'crop' => false );
            }

            return $sizes;
        }

        /**
         * Add the description of our own sizes as optional sizes in the wp media selector
         *
         * @param $sizes
         * @return array
         */
        public static function image_size_names_choose( $sizes ) {
            $custom_sizes = array(
//                'swifty_content' => __( 'Swifty Content', 'swifty-site' )
            );

            foreach( SwiftyImageFunctions::image_sizes_to_generate() as $size ) {
                $custom_sizes[ $size[ 0 ] ] = $size[ 2 ];
            }

            $new_sizes = array_merge( $sizes, $custom_sizes );

            return $new_sizes;
        }

    }
}