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

	function providerList() {
		var providers = settings.providers || {};
		return Object.keys( providers ).map( function ( id ) {
			return {
				id: id,
				label: providers[ id ].label || id,
				url: providers[ id ].url || '',
			};
		} );
	}

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
		var mark = settings.brandMarkUrl || settings.logoUrl;
		if ( mark ) {
			return el(
				'span',
				{ className: 'fimipay-blocks-label-wrap', style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
				el( 'img', {
					src: mark,
					alt: '',
					style: { height: '28px', width: '28px', borderRadius: '8px', objectFit: 'contain' },
				} ),
				el( 'span', { style: { fontWeight: 800 } }, title )
			);
		}
		return title;
	}

	function BrandHeader() {
		return el(
			'div',
			{ className: 'fimipay-checkout-card__brand' },
			settings.logoUrl
				? el( 'img', {
						className: 'fimipay-checkout-card__logo fimipay-checkout-card__logo--lg',
						src: settings.logoUrl,
						alt: 'FimiPay',
				  } )
				: el( 'strong', { className: 'fimipay-brand-text' }, 'FimiPay' ),
			settings.testMode ? el( 'span', { className: 'fimipay-checkout-card__mode' }, 'Test mode' ) : null
		);
	}

	function PhoneField( props ) {
		return el(
			'div',
			null,
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
					value: props.phone,
					onChange: function ( event ) {
						props.setPhone( toLocalNine( event.target.value ) );
					},
					autoComplete: 'tel-national',
					required: true,
				} )
			),
			el( 'p', { className: 'fimipay-phone-hint' }, settings.phoneHint || 'Enter the phone number linked to your wallet to pay.' )
		);
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
		var style = settings.checkoutStyle === 'default' ? 'default' : 'fimipay';
		var providers = providerList();

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
							message: 'Please enter your mobile money phone number to pay.',
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

		if ( style === 'default' ) {
			return el(
				'div',
				{ className: 'fimipay-checkout-card fimipay-blocks-fields fimipay-style-default', 'data-style': 'default' },
				el( BrandHeader, null ),
				settings.description
					? el( 'p', { className: 'fimipay-checkout-card__desc' }, decodeEntities( settings.description ) )
					: el(
							'p',
							{ className: 'fimipay-checkout-card__desc' },
							'Enter your mobile money phone number below, then tap Pay. You will get a prompt on your phone to approve.'
					  ),
				el( PhoneField, { phone: phone, setPhone: setPhone } ),
				el(
					'div',
					{ className: 'fimipay-providers-row' },
					providers.map( function ( p ) {
						return el(
							'span',
							{ className: 'fimipay-providers-row__item', key: p.id },
							el( 'img', { className: 'fimipay-provider-logo', src: p.url, alt: p.label } )
						);
					} )
				)
			);
		}

		return el(
			'div',
			{ className: 'fimipay-checkout-card fimipay-blocks-fields fimipay-style-fimipay', 'data-style': 'fimipay' },
			el( BrandHeader, null ),
			el( 'hr', { className: 'fimipay-rule' } ),
			el(
				'div',
				{ className: 'fimipay-complete' },
				el( 'div', { className: 'fimipay-complete__label' }, settings.completeLabel || 'Complete Payment' ),
				el( 'div', { className: 'fimipay-complete__amount' }, settings.amountLabel || '—' ),
				el( 'div', { className: 'fimipay-complete__merchant-label' }, settings.merchantLabel || 'Merchant' ),
				el( 'div', { className: 'fimipay-complete__merchant' }, settings.merchantName || 'Store' )
			),
			el( 'hr', { className: 'fimipay-rule' } ),
			el( 'div', { className: 'fimipay-section-label' }, settings.methodLabel || 'Choose Payment Method' ),
			el(
				'div',
				{ className: 'fimipay-method-grid' },
				providers.map( function ( method ) {
					return el(
						'button',
						{
							type: 'button',
							key: method.id,
							className: 'fimipay-method-chip fimipay-method-chip--logo' + ( channel === method.id ? ' is-active' : '' ),
							'aria-label': method.label,
							'aria-pressed': channel === method.id ? 'true' : 'false',
							title: method.label,
							onClick: function () {
								setChannel( method.id );
							},
						},
						el( 'img', { className: 'fimipay-provider-logo', src: method.url, alt: method.label } )
					);
				} )
			),
			el( 'hr', { className: 'fimipay-rule' } ),
			el( PhoneField, { phone: phone, setPhone: setPhone } ),
			el( 'hr', { className: 'fimipay-rule' } ),
			el(
				'div',
				{ className: 'fimipay-secure-note fimipay-secure-note--center' },
				el( 'span', { className: 'fimipay-secure-note__icon', 'aria-hidden': 'true' }, '🔒' ),
				el( 'span', null, settings.secureNote || 'Secure checkout powered by FimiPay' )
			)
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
