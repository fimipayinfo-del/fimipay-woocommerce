/**
 * Classic checkout helpers for FimiPay.
 * Never reads or stores secret API keys in the browser.
 */
( function ( $ ) {
	'use strict';

	function digitsOnly( value ) {
		return String( value || '' ).replace( /\D+/g, '' );
	}

	function toLocalNine( value ) {
		var d = digitsOnly( value );
		if ( d.indexOf( '255' ) === 0 && d.length >= 12 ) {
			d = d.slice( 3 );
		}
		if ( d.charAt( 0 ) === '0' ) {
			d = d.slice( 1 );
		}
		return d.slice( 0, 9 );
	}

	function toMsisdn( local ) {
		var nine = toLocalNine( local );
		if ( nine.length !== 9 ) {
			return '';
		}
		return '255' + nine;
	}

	function formatDisplay( local ) {
		var d = toLocalNine( local );
		if ( d.length <= 3 ) {
			return d;
		}
		if ( d.length <= 6 ) {
			return d.slice( 0, 3 ) + ' ' + d.slice( 3 );
		}
		return d.slice( 0, 3 ) + ' ' + d.slice( 3, 6 ) + ' ' + d.slice( 6 );
	}

	function syncPhone() {
		var $local = $( '#fimipay_phone_local' );
		var $hidden = $( '#fimipay_phone' );
		if ( ! $local.length || ! $hidden.length ) {
			return;
		}
		var local = toLocalNine( $local.val() );
		var caretEnd = $local[ 0 ].selectionEnd;
		$local.val( formatDisplay( local ) );
		$hidden.val( toMsisdn( local ) );
		try {
			$local[ 0 ].setSelectionRange( caretEnd, caretEnd );
		} catch ( e ) {
			/* ignore */
		}
	}

	$( document.body ).on( 'input blur', '#fimipay_phone_local', syncPhone );
	$( document.body ).on( 'updated_checkout payment_method_selected', syncPhone );
	$( syncPhone );
} )( jQuery );
