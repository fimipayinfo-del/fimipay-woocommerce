/**
 * Admin media picker for Custom Logo.
 */
( function ( $ ) {
	'use strict';

	$( document ).on( 'click', '.fimipay-media-upload', function ( e ) {
		e.preventDefault();
		var target = $( this ).data( 'target' );
		var frame = wp.media( {
			title: 'Select FimiPay logo',
			button: { text: 'Use logo' },
			multiple: false,
		} );
		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			$( '#' + target ).val( attachment.url ).trigger( 'change' );
		} );
		frame.open();
	} );
} )( jQuery );
