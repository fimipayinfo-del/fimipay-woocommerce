/**
 * FimiPay payment method for WooCommerce Blocks checkout.
 * Public-safe data only — no secret keys.
 */
( function () {
	'use strict';

	if ( ! window.wc || ! window.wc.wcBlocksRegistry || ! window.wp || ! window.wp.element ) {
		return;
	}

	var settings = window.wc.wcSettings
		? window.wc.wcSettings.getSetting( 'fimipay_data', {} )
		: {};

	var registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
	var el = window.wp.element.createElement;
	var useState = window.wp.element.useState;
	var useEffect = window.wp.element.useEffect;
	var decodeEntities =
		window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities
			? window.wp.htmlEntities.decodeEntities
			: function ( text ) {
					return text;
			  };

	function toLocalNine( value ) {
		var d = String( value || '' ).replace( /\D+/g, '' );
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
		return nine.length === 9 ? '255' + nine : '';
	}

	function Label() {
		var title = decodeEntities( settings.title || 'FimiPay' );
		if ( settings.logoUrl ) {
			return el(
				'span',
				{ className: 'fimipay-blocks-label-wrap' },
				el( 'img', {
					src: settings.logoUrl,
					alt: '',
					style: { height: '20px', marginRight: '8px', verticalAlign: 'middle' },
				} ),
				title
			);
		}
		return title;
	}

	function Content( props ) {
		var eventRegistration = props.eventRegistration || {};
		var emitResponse = props.emitResponse || {};
		var onPaymentSetup = eventRegistration.onPaymentSetup;
		var phoneState = useState( '' );
		var phone = phoneState[ 0 ];
		var setPhone = phoneState[ 1 ];

		useEffect(
			function () {
				if ( ! onPaymentSetup ) {
					return undefined;
				}

				return onPaymentSetup( function () {
					var msisdn = toMsisdn( phone );
					if ( ! msisdn ) {
						return {
							type: emitResponse.responseTypes.ERROR,
							message: 'Please enter a valid mobile money phone number.',
						};
					}

					return {
						type: emitResponse.responseTypes.SUCCESS,
						meta: {
							paymentMethodData: {
								fimipay_phone: msisdn,
							},
						},
					};
				} );
			},
			[ phone, onPaymentSetup, emitResponse ]
		);

		return el(
			'div',
			{ className: 'fimipay-checkout-card fimipay-blocks-fields' },
			settings.description
				? el( 'p', { className: 'fimipay-checkout-card__desc' }, decodeEntities( settings.description ) )
				: null,
			el(
				'div',
				{ className: 'fimipay-method-row', 'aria-hidden': 'true' },
				[ 'M-Pesa', 'Airtel', 'Mixx', 'HaloPesa' ].map( function ( label ) {
					return el( 'span', { className: 'fimipay-method-chip', key: label }, label );
				} )
			),
			el(
				'label',
				{ htmlFor: 'fimipay-blocks-phone', className: 'fimipay-field-label' },
				settings.phoneLabel || 'Mobile money number'
			),
			el(
				'div',
				{ className: 'fimipay-phone-shell' },
				el(
					'div',
					{ className: 'fimipay-phone-shell__prefix' },
					el( 'span', { className: 'fimipay-flag', 'aria-hidden': 'true' }, '🇹🇿' ),
					el( 'span', null, settings.dialCode || '+255' )
				),
				el( 'input', {
					id: 'fimipay-blocks-phone',
					type: 'tel',
					inputMode: 'tel',
					className: 'fimipay-phone-shell__input',
					placeholder: settings.phonePlaceholder || '7XX XXX XXX',
					value: phone,
					onChange: function ( event ) {
						setPhone( toLocalNine( event.target.value ) );
					},
					autoComplete: 'tel-national',
					required: true,
				} )
			),
			el( 'p', { className: 'fimipay-phone-hint' }, settings.phoneHint || '' )
		);
	}

	registerPaymentMethod( {
		name: 'fimipay',
		label: el( Label, null ),
		content: el( Content, null ),
		edit: el( Content, null ),
		canMakePayment: function () {
			return true;
		},
		ariaLabel: decodeEntities( settings.title || 'FimiPay' ),
		supports: {
			features: settings.supports || [ 'products' ],
		},
	} );
} )();
