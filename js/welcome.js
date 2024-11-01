( function( $, window, document, undefined ) {

    $( '.swc_btn_welcome_install' ).on( 'click', function() {

        $( this ).hide();

        $( '.swc_welcome_spinner' ).show();

        var $status = $( '.swc_welcome_status' );
        $status.html( '<b>' + ssm_wlc_data.i18n.preparing + '...</b>' );
        $status.show();

        $.when(
            getData( 'swifty_get_active_swifty_plugins' ),
            getData( 'swifty_get_plugin_versions' ),
            getData( 'swifty_get_active_theme' )
        ).done( function( plugins, versions, theme ) {
            if( plugins && versions && theme &&
                plugins.data && versions.data && theme.data &&
                plugins.success && versions.success && theme.success
            ) {
                installOrUpdatePlugins( 0, $status, plugins, versions, theme );
            } else {
                processError( ssm_wlc_data.i18n.installation_failed );
            }
        } ).fail( function() {
            processError( ssm_wlc_data.i18n.installation_failed );
        } );
    } );

    $( '.swc_btn_welcome_ready' ).on( 'click', function() {
        window.location.href = ssm_wlc_data.design_url;
    } );

    $( '.swc_welcome_note > a, .swc_welcome_home > a, .swc_btn_welcome_close' ).on( 'click', function() {
        window.location.href = ssm_wlc_data.home_url;
    } );

    var getData = function( action ) {
        var dfd = new $.Deferred();

        $.post(
            ssm_wlc_data.ajax_url,
            {
                'action': action,
                'ajax_nonce': ssm_wlc_data.ajax_nonce
            }
        ).done( function( data ) {
            dfd.resolve( data );
        } );

        return dfd;
    };

    var errorOccured = 0;

    var installOrUpdatePlugins = function( index, $status, plugins, versions, theme ) {
        if( index < ssm_wlc_data.swifty_plugins.length ) {
            var plugin = ssm_wlc_data.swifty_plugins[ index ];

            if( errorOccured ) {
                return false;
            }

            var wpType = plugin.slug === 'swifty-site-designer' ? 'theme' : 'plugin';

            // Plugin is installed, maybe an update is needed.
            if( $.inArray( plugin.slug, plugins.data ) > -1 ) {
                if( versions.data[ plugin.slug ] &&
                    versions.data[ plugin.slug ].update_status &&
                    versions.data[ plugin.slug ].update_status === 'update_available'
                ) {
                    $status.html( '<b>' + ssm_wlc_data.i18n.updating + ' ' + plugin.name + '</b>' );

                    var updateData = {
                        '_ajax_nonce': ssm_wlc_data.ajax_updates_nonce,
                        'plugin': versions.data[ plugin.slug ].update_plugin,
                        'slug': versions.data[ plugin.slug ].update_slug
                    };

                    if( wpType === 'theme' ) {
                        updateData = {
                            '_ajax_nonce': ssm_wlc_data.ajax_nonce,
                            'action': 'swifty_upgrade_ssd'
                        };

                        $.post(
                            ssm_wlc_data.ajax_url,
                            updateData
                        ).done( function( data ) {
                            if( ! data || ( data && ! data.success ) ) {
                                errorOccured = 1;
                                processError( ssm_wlc_data.i18n.install_failed, plugin.name );
                            } else {
                                index++;
                                installOrUpdatePlugins( index, $status, plugins, versions, theme );
                            }
                        } ).fail( function() {
                            errorOccured = 1;
                            processError( ssm_wlc_data.i18n.update_failed, plugin.name );
                        } );
                    } else {
                        wp.ajax.post(
                            'update-plugin',
                            updateData
                        ).done( function() {
                            index ++;
                            installOrUpdatePlugins( index, $status, plugins, versions, theme );
                        } ).fail( function() {
                            errorOccured = 1;
                            processError( ssm_wlc_data.i18n.update_failed, plugin.name );
                        } );
                    }
                } else {
                    index ++;
                    installOrUpdatePlugins( index, $status, plugins, versions, theme );
                }
            } else {   // Plugin not installed
                $status.html( '<b>' + ssm_wlc_data.i18n.installing + ' ' + plugin.name + '</b>' );

                var installData = {
                    'action': 'swifty_install_and_activate_plugin',
                    'ajax_nonce': ssm_wlc_data.ajax_nonce,
                    'plugin_slug': plugin.slug,
                    'activate': 0
                };

                if( wpType === 'theme' ) {
                    installData = {
                        'action': 'swifty_install_and_activate_ssd',
                        'ajax_nonce': ssm_wlc_data.ajax_nonce
                    }
                }

                $.post(
                    ssm_wlc_data.ajax_url,
                    installData
                ).done( function( data ) {
                    if( ! data || ( data && ! data.success ) ) {
                        errorOccured = 1;
                        processError( ssm_wlc_data.i18n.install_failed, plugin.name );
                    } else {
                        index++;
                        installOrUpdatePlugins( index, $status, plugins, versions, theme );
                    }
                } ).fail( function() {
                    errorOccured = 1;
                    processError( ssm_wlc_data.i18n.install_failed, plugin.name );
                } );
            }
        } else {
            $( '.swc_btn_welcome_install' ).hide();
            $( '.swc_welcome_note' ).hide();
            $( '.swc_welcome_status' ).hide();
            $( '.swc_welcome_spinner' ).hide();
            $( '.swc_welcome_ready' ).show();

        }
    };

    var processError = function( msg, pluginName ) {
        if( pluginName ) {
            msg = msg.replace( '<PLUGIN>', pluginName );
        }

        $( '.swc_welcome_error_msg' ).html( msg ).closest( '.swc_welcome_error' ).show();
    };

} )( jQuery, window, document );