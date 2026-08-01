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

	var METHODS = [
		{ id: 'mpesa', label: 'M-Pesa' },
		{ id: 'airtel', label: 'Airtel' },
		{ id: 'mixx', label: 'Mixx' },
		{ id: 'halopesa', label: 'HaloPesa' },
	];

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
		var channelState = useState( 'mpesa' );
		var channel = channelState[ 0 ];
		var setChannel = channelState[ 1 ];
		var style = settings.checkoutStyle || 'fimipay';

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
								fimipay_channel: channel,
							},
						},
					};
				} );
			},
			[ phone, channel, onPaymentSetup, emitResponse ]
		);

		if ( style === 'fimipay' ) {
			var amount = settings.amountLabel || '';
			return el(
				'div',
				{ className: 'fimipay-checkout-card fimipay-blocks-fields fimipay-style-fimipay', 'data-style': 'fimipay' },
				el(
					'div',
					{ className: 'fimipay-checkout-card__brand' },
					settings.logoUrl
						? el( 'img', { className: 'fimipay-checkout-card__logo', src: settings.logoUrl, alt: 'FimiPay' } )
						: el( 'strong', { className: 'fimipay-brand-text' }, 'FimiPay' ),
					settings.testMode ? el( 'span', { className: 'fimipay-checkout-card__mode' }, 'Test mode' ) : null
				),
				el( 'hr', { className: 'fimipay-rule' } ),
				el(
					'div',
					{ className: 'fimipay-complete' },
					el( 'div', { className: 'fimipay-complete__label' }, settings.completeLabel || 'Complete Payment' ),
					el( 'div', { className: 'fimipay-complete__amount' }, amount || '—' ),
					el( 'div', { className: 'fimipay-complete__merchant-label' }, settings.merchantLabel || 'Merchant' ),
					el( 'div', { className: 'fimipay-complete__merchant' }, settings.merchantName || 'Store' )
				),
				el( 'hr', { className: 'fimipay-rule' } ),
				el( 'div', { className: 'fimipay-section-label' }, settings.methodLabel || 'Choose Payment Method' ),
				el(
					'div',
					{ className: 'fimipay-method-grid' },
					METHODS.map( function ( method ) {
						return el(
							'button',
							{
								type: 'button',
								key: method.id,
								className: 'fimipay-method-chip' + ( channel === method.id ? ' is-active' : '' ),
								'aria-pressed': channel === method.id ? 'true' : 'false',
								onClick: function () {
									setChannel( method.id );
								},
							},
							method.label
						);
					} )
				),
				el( 'hr', { className: 'fimipay-rule' } ),
				el( 'label', { htmlFor: 'fimipay-blocks-phone', className: 'fimipay-field-label' }, settings.phoneLabel || 'Phone Number' ),
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
						placeholder: '712 345 678',
						value: phone,
						onChange: function ( event ) {
							setPhone( toLocalNine( event.target.value ) );
						},
						autoComplete: 'tel-national',
						required: true,
					} )
				),
				el( 'hr', { className: 'fimipay-rule' } ),
				el(
					'div',
					{ className: 'fimipay-secure-note fimipay-secure-note--center' },
					el( 'span', { className: 'fimipay-secure-note__icon', 'aria-hidden': 'true' }, '🔒' ),
					el( 'span', null, settings.secureNote || 'Secure checkout powered by FimiPay' )
				)
			);
		}

		var kids = [];
		if ( settings.description ) {
			kids.push(
				el( 'p', { className: 'fimipay-checkout-card__desc', key: 'desc' }, decodeEntities( settings.description ) )
			);
		}
		if ( style === 'midnight' ) {
			kids.push(
				el(
					'ul',
					{ className: 'fimipay-steps', 'aria-hidden': 'true', key: 'steps' },
					el( 'li', null, el( 'span', null, '1' ), 'Enter number' ),
					el( 'li', null, el( 'span', null, '2' ), 'Approve USSD' ),
					el( 'li', null, el( 'span', null, '3' ), 'Done' )
				)
			);
		}
		kids.push(
			el( 'label', { htmlFor: 'fimipay-blocks-phone', className: 'fimipay-field-label', key: 'label' }, settings.phoneLabel || 'Phone Number' )
		);
		kids.push(
			el(
				'div',
				{ className: 'fimipay-phone-shell', key: 'phone' },
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
			)
		);

		return el(
			'div',
			{
				className: 'fimipay-checkout-card fimipay-blocks-fields fimipay-style-' + style,
				'data-style': style,
			},
			kids
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
