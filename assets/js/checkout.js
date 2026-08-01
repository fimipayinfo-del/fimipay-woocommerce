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

	function refreshPayLabel() {
		var amount =
			( window.fimipayCheckout && fimipayCheckout.amountLabel ) ||
			$( '#fimipay-amount-label' ).text() ||
			'';
		var $btn = $( '#fimipay-pay-btn' );
		if ( $btn.length && amount ) {
			$btn.html(
				'<span class="fimipay-pay-btn__icon" aria-hidden="true">▶</span> Pay ' + amount
			);
		}
		if ( amount ) {
			$( '#fimipay-amount-label' ).text( amount );
		}
	}

	$( document.body ).on( 'input blur', '#fimipay_phone_local', syncPhone );
	$( document.body ).on( 'updated_checkout payment_method_selected', function () {
		syncPhone();
		refreshPayLabel();
	} );

	$( document.body ).on( 'click', '.fimipay-method-chip', function ( e ) {
		e.preventDefault();
		var $btn = $( this );
		var method = $btn.data( 'method' );
		$btn.closest( '.fimipay-method-grid' ).find( '.fimipay-method-chip' )
			.removeClass( 'is-active' )
			.attr( 'aria-pressed', 'false' );
		$btn.addClass( 'is-active' ).attr( 'aria-pressed', 'true' );
		$( '#fimipay_channel' ).val( method );
	} );

	$( document.body ).on( 'click', '#fimipay-pay-btn', function ( e ) {
		e.preventDefault();
		syncPhone();
		// Prefer FimiPay, then trigger WooCommerce place order.
		$( '#payment_method_fimipay' ).prop( 'checked', true ).trigger( 'click' );
		var $place = $( '#place_order' );
		if ( $place.length ) {
			$place.trigger( 'click' );
		}
	} );

	$( function () {
		syncPhone();
		refreshPayLabel();
	} );
} )( jQuery );
