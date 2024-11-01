// Set the sessions storage vars
jQuery( function( $ ) {
    // var used to go back from the page manager and the content creator
    $( '#sm-admin-bar-ss-pages,#sm-admin-bar-ss-settings' ).find( 'a' ).click( function( /*ev*/ ) {
        if( typeof Storage !== 'undefined' ) {
            sessionStorage.back_location = ssm_data.back_location;
            sessionStorage.spm_location = ssm_data.spm_location;
        }
    } );

    $( 'body' ).css( {
        'margin-bottom': $( '#smadminbar' ).outerHeight() + 'px'
    } );

    $( window ).on( 'resize', function() {
        $( 'body' ).css( {
            'margin-bottom': $( '#smadminbar' ).outerHeight() + 'px'
        } );
        $( '.swc_iframe_gradient' ).css( {
            'bottom': $( '#smadminbar' ).outerHeight() + 'px'
        } );
    } );
} );